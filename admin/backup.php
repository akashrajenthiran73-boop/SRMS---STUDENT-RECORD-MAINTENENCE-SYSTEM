<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin Check Only
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

$tables = ['users', 'study_materials', 'assignments', 'announcements', 'student_leaves'];

// --- 1. HANDLE BACKUP DOWNLOAD (JSON EXPORT) ---
if (isset($_GET['action']) && $_GET['action'] === 'export_backup') {
    $backup_data = [];

    foreach ($tables as $table) {
        $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/" . $table . "?select=*";
        $ch = curl_init($fetch_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY"
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $backup_data[$table] = json_decode($res, true) ?? [];
    }

    $file_name = 'srms_backup_' . date('Y-m-d_H-i-s') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    echo json_encode($backup_data, JSON_PRETTY_PRINT);
    exit();
}

// --- 2. HANDLE RESTORE (JSON IMPORT) ---
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore_backup') {
    if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
        $json_content = file_get_contents($_FILES['backup_file']['tmp_name']);
        $imported_data = json_decode($json_content, true);

        if (is_array($imported_data)) {
            $success_count = 0;
            foreach ($imported_data as $table_name => $rows) {
                if (in_array($table_name, $tables) && is_array($rows)) {
                    foreach ($rows as $row) {
                        // Remove primary key auto IDs if needed to avoid duplicate conflicts
                        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/" . $table_name;
                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($row));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            "apikey: $SUPABASE_KEY",
                            "Authorization: Bearer $SUPABASE_KEY",
                            "Content-Type: application/json",
                            "Prefer: resolution=merge-duplicates"
                        ]);
                        $res = curl_exec($ch);
                        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($code == 200 || $code == 201) {
                            $success_count++;
                        }
                    }
                }
            }
            $msg = "Database successfully restored!";
            $msg_type = "success";
        } else {
            $msg = "Invalid backup file format!";
            $msg_type = "error";
        }
    } else {
        $msg = "Please select a valid JSON backup file.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Backup & Restore - SRMS Portal</title>
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
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 28px;
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 20px;
}

.icon-export {
    background: #ECFDF5;
    color: #10B981;
    border: 1px solid #A7F3D0;
}

.icon-import {
    background: #EFF6FF;
    color: #2563EB;
    border: 1px solid #BFDBFE;
}

.card h3 {
    color: #1E3A8A;
    font-size: 19px;
    font-weight: 800;
    margin-bottom: 10px;
}

.card p {
    font-size: 13.5px;
    color: #64748B;
    line-height: 1.6;
    margin-bottom: 25px;
}

.card code {
    background: #F1F5F9;
    color: #0F172A;
    padding: 2px 6px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 12.5px;
}

.btn-backup {
    background: #10B981;
    color: white;
    border: none;
    padding: 13px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
.btn-backup:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

.btn-restore {
    background: #2563EB;
    color: white;
    border: none;
    padding: 13px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.btn-restore:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.file-input {
    margin-bottom: 22px;
    border: 2px dashed #CBD5E1;
    padding: 20px;
    border-radius: 12px;
    background: #F8FAFC;
    text-align: center;
    transition: all 0.2s ease;
}
.file-input:hover {
    border-color: #2563EB;
    background: #EFF6FF;
}
.file-input input {
    width: 100%;
    cursor: pointer;
}

.alert {
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin: 25px 40px 0 40px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; grid-template-columns: 1fr; }
    .alert { margin: 20px 20px 0 20px; }
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
            <li><a href="backup.php" class="active"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
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

<div class="main-content">
    <div class="navbar">
        <h1>Backup & Restore</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: Admin (<?php echo htmlspecialchars($user_email); ?>)
        </div>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fa-solid <?php echo ($msg_type === 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i> <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="content-body">
        
        <!-- EXPORT / BACKUP CARD -->
        <div class="card">
            <div>
                <div class="card-icon icon-export"><i class="fa-solid fa-cloud-arrow-down"></i></div>
                <h3>Export Database Backup</h3>
                <p>Download a full snapshot of your system data including Users, Study Materials, Assignments, Announcements, and Leave Records as a <code>.json</code> file.</p>
            </div>
            <a href="backup.php?action=export_backup" class="btn-backup">
                <i class="fa-solid fa-download"></i> Download Backup File
            </a>
        </div>

        <!-- IMPORT / RESTORE CARD -->
        <div class="card">
            <form action="backup.php" method="POST" enctype="multipart/form-data" style="height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <input type="hidden" name="action" value="restore_backup">
                
                <div>
                    <div class="card-icon icon-import"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <h3>Restore Database</h3>
                    <p>Upload a previously exported <code>.json</code> backup file to sync and restore all data tables back to Supabase.</p>
                    
                    <div class="file-input">
                        <input type="file" name="backup_file" accept=".json" required>
                    </div>
                </div>

                <button type="submit" class="btn-restore" onclick="return confirm('Are you sure you want to restore data? Existing records may be merged/updated.');">
                    <i class="fa-solid fa-rotate-left"></i> Restore Database
                </button>
            </form>
        </div>

    </div>
</div>

</body>
</html>