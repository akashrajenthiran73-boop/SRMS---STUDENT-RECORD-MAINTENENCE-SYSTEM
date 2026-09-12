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

// 1. Multi-location Safe .env File Loader
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

$bio_data_list = [];
$error = "";

// 2. Fetch Data from Supabase
$url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?select=*&order=id.desc";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $SUPABASE_KEY", 
    "Authorization: Bearer $SUPABASE_KEY"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    $error = "cURL Connection Error: " . $curl_error;
} else if ($http_code == 200) {
    $bio_data_list = json_decode($response, true);
    if (!is_array($bio_data_list)) $bio_data_list = [];
} else {
    $error = "Database Error: HTTP $http_code - " . $response;
}

require_once __DIR__ . '/../includes/college_data.php';
$departments = get_all_departments();
$selected_dept = $_GET['dept'] ?? 'All';
$selected_year = $_GET['year'] ?? 'All';

// 1. Filter by Department
if ($selected_dept !== 'All' && !empty($bio_data_list)) {
    $bio_data_list = array_filter($bio_data_list, function($b) use ($selected_dept) {
        $d = strtoupper($b['department'] ?? '');
        if ($selected_dept === 'CS') {
            return (strpos($d, 'CS') !== false || strpos($d, 'COMPUTER') !== false || empty($d));
        }
        return (strpos($d, strtoupper($selected_dept)) !== false);
    });
}

// 2. Filter by Academic Year & Degree Level
if ($selected_year !== 'All' && !empty($bio_data_list)) {
    $bio_data_list = array_filter($bio_data_list, function($b) use ($selected_year) {
        $info = get_student_year_info($b);
        return ($info['filter_tag'] === $selected_year || $info['level'] === $selected_year);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bio Data Management - SRMS</title>
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

/* Table Card Layout */
.table-card {
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
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.header-actions h2 {
    color: #1E3A8A;
    font-size: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.action-buttons {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
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

.btn-primary {
    background: #FFFFFF;
    color: #475569;
    border: 1.5px solid #E2E8F0;
}
.btn-primary:hover {
    background: #F8FAFC;
    color: #0F172A;
    border-color: #CBD5E1;
}

.btn-add {
    background: #2563EB;
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.btn-add:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.btn-view {
    background: #EFF6FF;
    color: #2563EB;
    border: 1px solid #BFDBFE;
    padding: 6px 12px;
    font-size: 12.5px;
    border-radius: 8px;
}
.btn-view:hover {
    background: #2563EB;
    color: white;
}

.btn-edit {
    background: #FFFBEB;
    color: #D97706;
    border: 1px solid #FDE68A;
    padding: 6px 12px;
    font-size: 12.5px;
    border-radius: 8px;
}
.btn-edit:hover {
    background: #F59E0B;
    color: white;
}

/* Alerts */
.alert {
    padding: 12px 18px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}
.alert-info {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
}

/* Table Design */
.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
    text-align: left;
}

th, td {
    padding: 14px 18px;
    border-bottom: 1px solid #F1F5F9;
    font-size: 13.5px;
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

tbody tr {
    transition: background 0.2s ease;
}

tbody tr:hover {
    background: #F8FAFC;
}

td {
    color: #334155;
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
            <div class="menu-heading">College Operations</div>
            <li><a href="manage_departments.php"><i class="fa-solid fa-building-columns"></i> <span>Manage Departments</span></a></li>
            <li><a href="circulars.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> <span>Reports</span></a></li>
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
        <h1>Bio Data Records</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <!-- DUAL FILTER BAR: DEPARTMENT & ACADEMIC YEAR -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
            <form method="GET" action="bio_data.php" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span style="font-size: 13px; font-weight: 700; color: #475569;"><i class="fa-solid fa-filter" style="color: #2563EB;"></i> Department:</span>
                <select name="dept" onchange="this.form.submit()" style="padding: 8px 14px; border: 1.5px solid #E2E8F0; border-radius: 8px; font-size: 13px; font-weight: 600; outline: none; background: #F8FAFC;">
                    <option value="All" <?php echo $selected_dept === 'All' ? 'selected' : ''; ?>>All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['code']; ?>" <?php echo $selected_dept === $d['code'] ? 'selected' : ''; ?>>
                            <?php echo $d['code'] . ' - ' . $d['name'] . ($d['code'] === 'CS' ? ' (Active)' : ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <span style="font-size: 13px; font-weight: 700; color: #475569; margin-left: 6px;"><i class="fa-solid fa-graduation-cap" style="color: #2563EB;"></i> Class / Year:</span>
                <select name="year" onchange="this.form.submit()" style="padding: 8px 14px; border: 1.5px solid #E2E8F0; border-radius: 8px; font-size: 13px; font-weight: 600; outline: none; background: #F8FAFC;">
                    <option value="All" <?=$selected_year === 'All' ? 'selected' : ''?>>All Years & Degrees</option>
                    <option value="UG_1" <?=$selected_year === 'UG_1' ? 'selected' : ''?>>UG - 1st Year (I Year)</option>
                    <option value="UG_2" <?=$selected_year === 'UG_2' ? 'selected' : ''?>>UG - 2nd Year (II Year)</option>
                    <option value="UG_3" <?=$selected_year === 'UG_3' ? 'selected' : ''?>>UG - 3rd Year (III Year)</option>
                    <option value="PG_1" <?=$selected_year === 'PG_1' ? 'selected' : ''?>>PG - 1st Year (I M.Sc/M.A/M.Com)</option>
                    <option value="PG_2" <?=$selected_year === 'PG_2' ? 'selected' : ''?>>PG - 2nd Year (II M.Sc/M.A/M.Com)</option>
                </select>

                <?php if ($selected_dept !== 'All' || $selected_year !== 'All'): ?>
                    <a href="bio_data.php" class="btn btn-light" style="padding: 6px 12px; font-size: 12px; background: #F1F5F9; border: 1px solid #CBD5E1; color: #475569; border-radius: 6px; text-decoration: none;"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
                <?php endif; ?>
            </form>
            <div style="font-size: 12.5px; color: #64748B;">
                Scope: <strong style="color: #1E40AF;"><?php echo ($selected_dept === 'All') ? 'All Depts' : ($selected_dept . ' Dept'); ?></strong>
                <?php if ($selected_year !== 'All'): ?>
                    &nbsp;|&nbsp; Year: <strong style="color: #7C3AED;"><?=$selected_year?></strong>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            
            <div class="header-actions">
                <h2><i class="fa-solid fa-clipboard-list" style="color: #2563EB;"></i> Bio Data Directory</h2>
                <div class="action-buttons">
                    <a href="dashboard_admin.php" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                    <a href="bio_data_form.php" class="btn btn-add"><i class="fa-solid fa-plus"></i> Add New Bio Data</a>
                </div>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert alert-error"><b>Error:</b> <?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="alert alert-info"><i class="fa-solid fa-circle-info"></i> <b>Total Records:</b> <?php echo count($bio_data_list); ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Department</th>
                            <th>Academic Class</th>
                            <th>Batch</th>
                            <th>Tamil Reg No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($bio_data_list)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 30px; color: #64748B;">No Records Found.</td></tr>
                        <?php else: ?>
                            <?php 
                            $i=1; 
                            foreach($bio_data_list as $row): 
                                $yinfo = get_student_year_info($row);
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight: 600; color: #1E293B;"><?php echo htmlspecialchars($row['name_ta_en'] ?? '-'); ?></td>
                                <td>
                                    <span style="background: #EFF6FF; color: #1D4ED8; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; display: inline-block;">
                                        <?php echo htmlspecialchars($row['department'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11.5px; padding: 4px 10px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 5px;">
                                        <i class="fa-solid <?=$yinfo['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($yinfo['short'])?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['academic_year'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['tamil_reg_no'] ?? '-'); ?></td>
                                <td>
                                    <a href="bio_data_view.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-view"><i class="fa-solid fa-eye"></i> View</a>
                                    <a href="bio_data_form.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-edit"><i class="fa-solid fa-pen"></i> Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>

</body>
</html>