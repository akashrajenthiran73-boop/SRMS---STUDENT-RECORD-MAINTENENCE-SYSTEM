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
        <span>Arignar Anna College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
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
        <h1>Student Directory</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <!-- PAGE HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-users" style="color: #2563EB;"></i> Total Students: <?php echo is_array($students) ? count($students) : 0; ?></h2>
            <div class="header-actions">
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Back</a>
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
                            <th>Course</th>
                            <th>Roll No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (!empty($students) && is_array($students)): 
                            $i = 1; 
                            foreach ($students as $s): 
                        ?>
                        <tr>
                            <td><strong><?php echo $i++; ?></strong></td>
                            <td><span style="font-weight: 600; color: #1E40AF;"><?php echo htmlspecialchars($s['admission_no'] ?? '-'); ?></span></td>
                            <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($s['name'] ?? '-'); ?></strong></td>
                            <td><span class="badge-course"><?php echo htmlspecialchars($s['course'] ?? '-'); ?></span></td>
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
                            <td colspan="6" style="text-align:center; padding: 50px 20px; color: #64748B;">
                                <i class="fa-solid fa-folder-open" style="font-size: 36px; margin-bottom: 12px; display: block; color: #CBD5E1;"></i>
                                No student records found in the database.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

</body>
</html>