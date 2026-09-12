<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session authorization (Updated to allow Admin & Super Admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) { 
    header("Location: ../auth/login.php"); 
    exit(); 
} 

$role = $_SESSION['role'];

// Load .env configuration from root folder safely
$env_path = '../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Fetch all students from Supabase via cURL
$students = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $url = $SUPABASE_URL . "/rest/v1/students?select=*";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $students = json_decode($response, true) ?? [];
}

require_once __DIR__ . '/../includes/college_data.php';
$departments = get_all_departments();
$selected_dept = $_GET['dept'] ?? 'All';
$selected_year = $_GET['year'] ?? 'All';

$lan_ip = gethostbyname(gethostname());
$server_port = $_SERVER['SERVER_PORT'] ?? '8000';
$lan_base_url = "http://" . $lan_ip . ($server_port != '80' && $server_port != '443' ? ":$server_port" : "");

// 1. Filter by Department
if ($selected_dept !== 'All' && !empty($students)) {
    $students = array_filter($students, function($s) use ($selected_dept) {
        $c = strtoupper($s['course'] ?? '');
        if ($selected_dept === 'CS') {
            return (strpos($c, 'CS') !== false || strpos($c, 'COMPUTER') !== false || strpos($c, 'B.SC') !== false || empty($c));
        }
        return (strpos($c, strtoupper($selected_dept)) !== false);
    });
}

// 2. Filter by Academic Year & Degree Level
if ($selected_year !== 'All' && !empty($students)) {
    $students = array_filter($students, function($s) use ($selected_year) {
        $info = get_student_year_info($s);
        return ($info['filter_tag'] === $selected_year || $info['level'] === $selected_year);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Records - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #0F172A;
    display: flex;
    min-height: 100vh;
}

/* Modern Sidebar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #0B132B;
    color: white;
    padding: 22px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.06);
    z-index: 100;
}

.sidebar-brand {
    text-align: center;
    padding-bottom: 18px;
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
    display: block;
    margin-top: 4px;
}

.sidebar-nav-container {
    flex-grow: 1;
    overflow-y: auto;
    margin-top: 14px;
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
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 10px 14px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.sidebar-menu a:hover {
    color: #F8FAFC;
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(3px);
}

.sidebar-menu a.active {
    background: rgba(245, 158, 11, 0.14);
    color: #F59E0B;
    font-weight: 700;
}

.sidebar-menu a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.menu-divider {
    margin: 14px 8px;
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

/* Main Content Wrapper */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Navbar */
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

/* Content Body */
.content-body {
    padding: 35px 40px;
    flex: 1;
}

.page-header-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 22px 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
    gap: 15px;
}

.page-header-card h2 {
    font-size: 19px;
    color: #1E3A8A;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

/* Modern Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.25s ease;
    cursor: pointer;
    border: none;
}
.btn-primary { background: #2563EB; color: #FFFFFF; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-primary:hover { background: #1D4ED8; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }

.btn-success { background: #10B981; color: #FFFFFF; padding: 7px 14px; font-size: 12.5px; border-radius: 8px; }
.btn-success:hover { background: #059669; transform: translateY(-1px); }

.btn-warning { background: #F59E0B; color: #FFFFFF; padding: 7px 14px; font-size: 12.5px; border-radius: 8px; }
.btn-warning:hover { background: #D97706; transform: translateY(-1px); }

.btn-light { background: #FFFFFF; color: #475569; border: 1.5px solid #E2E8F0; }
.btn-light:hover { background: #F8FAFC; color: #0F172A; border-color: #CBD5E1; }

/* Table Container Card */
.table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

th, td {
    padding: 16px 22px;
    font-size: 13.5px;
}

th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    border-bottom: 1.5px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
}

td {
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
}

tr:hover td {
    background: #F8FAFC;
}

.badge-course {
    background: #EFF6FF;
    color: #1D4ED8;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 12px;
    display: inline-block;
}

.action-btns {
    display: flex;
    gap: 8px;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 16px 8px; }
    .sidebar-brand h2 span, .sidebar-brand span, .sidebar-menu a span, .menu-heading { display: none; }
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
            <li><a href="student_records.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
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
        <h1>Student Directory</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <!-- DUAL FILTER BAR: DEPARTMENT & ACADEMIC YEAR -->
        <div style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 14px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 15px rgba(0,0,0,0.02);">
            <form method="GET" action="student_records.php" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
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
                    <a href="student_records.php" class="btn btn-light" style="padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
                <?php endif; ?>
            </form>
            <div style="font-size: 12.5px; color: #64748B;">
                Scope: <strong style="color: #1E40AF;"><?php echo ($selected_dept === 'All') ? 'All Depts' : ($selected_dept . ' Dept'); ?></strong>
                <?php if ($selected_year !== 'All'): ?>
                    &nbsp;|&nbsp; Year: <strong style="color: #7C3AED;"><?=$selected_year?></strong>
                <?php endif; ?>
            </div>
        </div>

        <!-- PAGE HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-users" style="color: #2563EB;"></i> Total Filtered Students: <?php echo is_array($students) ? count($students) : 0; ?></h2>
            <div class="header-actions">
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Back</a>
                <button type="button" class="btn btn-warning" onclick="openShareModal()"><i class="fa-solid fa-share-nodes"></i> Share Form Link</button>
                <a href="add_student.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add New Student</a>
            </div>
        </div>

        <!-- STUDENTS DATA TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Admission No</th>
                            <th>Name</th>
                            <th>Course & Dept</th>
                            <th>Academic Class</th>
                            <th>Roll No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($students) && is_array($students)): 
                            $i = 1; 
                            foreach ($students as $s): 
                                $yinfo = get_student_year_info($s);
                        ?>
                        <tr>
                            <td><strong><?php echo $i++; ?></strong></td>
                            <td><span style="font-weight: 600; color: #1E40AF;"><?php echo htmlspecialchars($s['admission_no'] ?? '-'); ?></span></td>
                            <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($s['name'] ?? '-'); ?></strong></td>
                            <td><span class="badge-course"><?php echo htmlspecialchars($s['course'] ?? '-'); ?></span></td>
                            <td>
                                <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11.5px; padding: 4px 10px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 5px;">
                                    <i class="fa-solid <?=$yinfo['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($yinfo['short'])?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($s['roll_no'] ?? '-'); ?></td>
                            <td>
                               <div class="action-btns">
                                   <a href="view_students.php?id=<?php echo urlencode($s['id'] ?? ''); ?>" class="btn btn-success"><i class="fa-solid fa-eye"></i> View</a>
                                   <a href="edit_student.php?id=<?php echo urlencode($s['id'] ?? ''); ?>" class="btn btn-warning"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                               </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 50px 20px; color: #64748B;">
                                <i class="fa-solid fa-folder-open" style="font-size: 36px; margin-bottom: 12px; display: block; color: #CBD5E1;"></i>
                                No student records found matching the selected department and academic year.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- SHARE STUDENT FORM LINK MODAL -->
<div id="shareModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:18px; max-width:560px; width:92%; padding:24px; box-shadow:0 20px 40px rgba(0,0,0,0.15); max-height:92vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #E2E8F0; padding-bottom:12px;">
            <h3 style="font-size:17px; font-weight:800; color:#1E3A8A; display:flex; align-items:center; gap:8px; margin:0;">
                <i class="fa-solid fa-share-nodes" style="color:#2563EB;"></i> Share Student Registration Link
            </h3>
            <button onclick="closeShareModal()" style="background:none; border:none; font-size:18px; color:#64748B; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <p style="font-size:12.5px; color:#64748B; margin-bottom:14px;">
            Generate a link for students to fill their <b>Student Record</b>, <b>Bio Data</b>, and <b>UMIS Details</b>.
        </p>

        <!-- DEVICE ACCESS MODE SELECTOR -->
        <div style="background:#F8FAFC; border:1.5px solid #E2E8F0; border-radius:12px; padding:12px; margin-bottom:14px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <label style="font-size:12px; font-weight:700; color:#1E293B; margin:0; display:flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-mobile-screen-button" style="color:#2563EB;"></i> Student Mobile Access Mode:
                </label>
                <span id="access_badge" style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:6px; background:#FEF3C7; color:#92400E;">💻 Local PC Only</span>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; margin-bottom:8px;">
                <button type="button" id="btn_mode_local" onclick="selectAccessMode('local')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    💻 Localhost
                </button>
                <button type="button" id="btn_mode_wifi" onclick="selectAccessMode('wifi')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    📶 Wi-Fi / Hotspot
                </button>
                <button type="button" id="btn_mode_public" onclick="selectAccessMode('public')" style="padding:7px 6px; font-size:11px; font-weight:600; border-radius:7px; border:1.5px solid #CBD5E1; background:white; cursor:pointer; text-align:center;">
                    🌐 Public / Tunnel
                </button>
            </div>
            <div style="margin-bottom:6px;">
                <input type="text" id="custom_domain_input" oninput="onCustomDomainChange()" placeholder="Enter Base URL (e.g. <?= htmlspecialchars($lan_base_url) ?> or https://...trycloudflare.com)" style="width:100%; padding:7px 10px; font-size:12px; border:1.5px solid #CBD5E1; border-radius:6px; font-family:monospace; background:white; box-sizing:border-box;">
            </div>
            <div id="mode_help_text" style="font-size:11px; color:#64748B; line-height:1.4;">
                ⚠️ <b>Localhost:</b> Only opens on this laptop. Choose <b>Wi-Fi / Hotspot</b> for mobile phones connected to same Wi-Fi or laptop hotspot.
            </div>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div>
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Select Department:</label>
                <select id="modal_dept" onchange="updateShareLink()" style="width:100%; padding:8px 10px; border:1.5px solid #CBD5E1; border-radius:8px; font-size:13px;">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?=$d['code']?>"><?=$d['code']?> - <?=$d['name']?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Select Class / Year:</label>
                <select id="modal_year" onchange="updateShareLink()" style="width:100%; padding:8px 10px; border:1.5px solid #CBD5E1; border-radius:8px; font-size:13px;">
                    <option value="">All Years</option>
                    <option value="UG_1">UG - 1st Year (I Year)</option>
                    <option value="UG_2">UG - 2nd Year (II Year)</option>
                    <option value="UG_3">UG - 3rd Year (III Year)</option>
                    <option value="PG_1">PG - 1st Year (I PG)</option>
                    <option value="PG_2">PG - 2nd Year (II PG)</option>
                </select>
            </div>
        </div>
        <div style="margin-bottom:14px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;">Shareable Link (Click to Copy):</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="modal_link_input" readonly style="flex:1; padding:8px 12px; font-size:12.5px; background:#F8FAFC; border:1.5px solid #CBD5E1; border-radius:8px; font-family:monospace;">
                <button type="button" onclick="copyShareLink()" class="btn btn-primary" style="padding:8px 14px; font-size:12.5px;"><i class="fa-solid fa-copy"></i> Copy</button>
            </div>
        </div>
        <div style="background:#F0FDF4; border:1px solid #BBF7D0; padding:12px; border-radius:10px; margin-bottom:16px;">
            <span style="font-size:12px; font-weight:700; color:#166534; display:block; margin-bottom:4px;"><i class="fa-brands fa-whatsapp" style="color:#16A34A;"></i> WhatsApp Ready Message:</span>
            <p id="whatsapp_text_preview" style="font-size:11.5px; color:#14532D; white-space:pre-wrap; margin-bottom:8px; font-family:sans-serif;"></p>
            <button type="button" onclick="copyWhatsAppMessage()" class="btn" style="background:#16A34A; color:white; width:100%; justify-content:center; padding:8px; font-size:12.5px;"><i class="fa-brands fa-whatsapp"></i> Copy WhatsApp Message</button>
        </div>
        <div style="text-align:right;">
            <button onclick="closeShareModal()" class="btn btn-light" style="padding:7px 16px;">Close</button>
        </div>
    </div>
</div>

<script>
const serverLanUrl = '<?= $lan_base_url ?>';
const isLiveDomain = (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1');
const defaultMode = isLiveDomain ? 'public' : 'wifi';
let currentMode = localStorage.getItem('srms_access_mode') || defaultMode;

function openShareModal() {
    document.getElementById('shareModal').style.display = 'flex';
    selectAccessMode(currentMode);
}

function closeShareModal() {
    document.getElementById('shareModal').style.display = 'none';
}

function selectAccessMode(mode) {
    currentMode = mode;
    localStorage.setItem('srms_access_mode', mode);

    const btnLocal = document.getElementById('btn_mode_local');
    const btnWifi = document.getElementById('btn_mode_wifi');
    const btnPublic = document.getElementById('btn_mode_public');
    const input = document.getElementById('custom_domain_input');
    const badge = document.getElementById('access_badge');
    const help = document.getElementById('mode_help_text');

    // Reset styles
    [btnLocal, btnWifi, btnPublic].forEach(b => {
        b.style.background = '#FFFFFF';
        b.style.color = '#334155';
        b.style.borderColor = '#CBD5E1';
    });

    if (mode === 'local') {
        btnLocal.style.background = '#2563EB';
        btnLocal.style.color = '#FFFFFF';
        btnLocal.style.borderColor = '#2563EB';
        input.value = window.location.origin;
        input.readOnly = true;
        badge.innerText = '💻 Local PC Only';
        badge.style.background = '#FEF3C7';
        badge.style.color = '#92400E';
        help.innerHTML = '⚠️ <b>Localhost:</b> Only opens on this laptop. Students on mobile will <b>not</b> be able to open this.';
    } else if (mode === 'wifi') {
        btnWifi.style.background = '#2563EB';
        btnWifi.style.color = '#FFFFFF';
        btnWifi.style.borderColor = '#2563EB';
        input.value = serverLanUrl;
        input.readOnly = true;
        badge.innerText = '📶 Same Wi-Fi / Hotspot';
        badge.style.background = '#DBEAFE';
        badge.style.color = '#1E40AF';
        help.innerHTML = '📶 <b>Same Wi-Fi / Hotspot:</b> Students can open this link on their mobile if connected to your <b>Laptop Hotspot</b> or the same <b>College Wi-Fi</b>.';
    } else if (mode === 'public') {
        btnPublic.style.background = '#2563EB';
        btnPublic.style.color = '#FFFFFF';
        btnPublic.style.borderColor = '#2563EB';
        input.readOnly = false;
        let savedPublic = localStorage.getItem('srms_custom_public_url') || (isLiveDomain ? window.location.origin : '');
        input.value = savedPublic;
        badge.innerText = '🌐 Public / Live';
        badge.style.background = '#DCFCE7';
        badge.style.color = '#15803D';
        help.innerHTML = isLiveDomain
            ? '🌐 <b>Live Cloud Domain:</b> Running on Render! Links work on <b>all student mobile phones anywhere</b>.'
            : '🌐 <b>Public / Tunnel URL:</b> Paste Cloudflare Tunnel, Render URL, Localtunnel, or Live Domain here. Students can open from <b>any mobile anywhere</b> (Jio, Airtel, etc.)!';
        if (!savedPublic) {
            input.focus();
        }
    }

    updateShareLink();
}

function onCustomDomainChange() {
    if (currentMode === 'public') {
        localStorage.setItem('srms_custom_public_url', document.getElementById('custom_domain_input').value.trim());
    }
    updateShareLink();
}

function updateShareLink() {
    const dept = document.getElementById('modal_dept').value;
    const year = document.getElementById('modal_year').value;
    
    let base = document.getElementById('custom_domain_input')?.value.trim() || window.location.origin;
    base = base.replace(/\/+$/, ''); // Strip trailing slash

    let portalPath = '/portal/student_entry.php';
    if (window.location.pathname.includes('/SRMS/')) {
        portalPath = '/SRMS/portal/student_entry.php';
    }

    // Guard against full URL pasted in domain input
    let url = base.includes('/portal/student_entry.php') ? base : (base + portalPath);

    const params = [];
    if (dept) params.push('dept=' + encodeURIComponent(dept));
    if (year) params.push('year=' + encodeURIComponent(year));
    if (params.length > 0) {
        url += (url.includes('?') ? '&' : '?') + params.join('&');
    }
    document.getElementById('modal_link_input').value = url;

    let targetDesc = "All Students";
    if (dept && year) targetDesc = `${year} (${dept})`;
    else if (dept) targetDesc = `Department of ${dept}`;
    else if (year) targetDesc = `${year}`;

    const msg = `🎓 *Arignar Anna Govt Arts College, Villupuram*\n\n📢 *Attention ${targetDesc} Students:*\nPlease fill your official *Student Record, Bio Data & UMIS details* online using this link:\n👉 ${url}\n\n⚠️ *Mandatory Details:* Please keep your Register Number, Email, EMIS No & Bank Details ready before submitting.`;
    document.getElementById('whatsapp_text_preview').innerText = msg;
}

function copyShareLink() {
    const link = document.getElementById('modal_link_input').value;
    navigator.clipboard.writeText(link).then(() => alert("Link copied to clipboard!"));
}

function copyWhatsAppMessage() {
    const msg = document.getElementById('whatsapp_text_preview').innerText;
    navigator.clipboard.writeText(msg).then(() => alert("WhatsApp message copied to clipboard!"));
}
</script>

</body>
</html>