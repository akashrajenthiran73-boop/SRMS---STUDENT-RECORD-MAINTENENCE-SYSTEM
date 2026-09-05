<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if(!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])){ 
    header("Location: ../auth/login.php"); 
    exit; 
}

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

function callSupabase($url, $key, $method = 'GET', $data = []){
    if (empty($url) || empty($key)) {
        return [[], 0];
    }
    $ch = curl_init($url);
    $headers = [
        "apikey: $key", 
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH') {
        $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'DELETE') {
        $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }

    curl_setopt_array($ch, $options);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($res, true), $http];
}

$msg = '';
$error = '';

// Handle Form Actions (Add, Edit, Reset Password, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = password_hash(trim($_POST['password'] ?? ''), PASSWORD_BCRYPT);
        $role = trim($_POST['role'] ?? '');
        $status = 'Active';

        if ($name && $email && $role) {
            $payload = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'status' => $status
            ];
            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users", $SUPABASE_KEY, 'POST', $payload);
            if ($http >= 200 && $http < 300) {
                $msg = "User added successfully!";
            } else {
                $error = "Failed to add user. Email might already exist.";
            }
        } else {
            $error = "All fields are required.";
        }
    } 
    elseif ($action === 'edit') {
        $id = $_POST['user_id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if ($id && $name && $email && $role) {
            $payload = [
                'name' => $name,
                'email' => $email,
                'role' => $role
            ];
            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq.$id", $SUPABASE_KEY, 'PATCH', $payload);
            if ($http >= 200 && $http < 300) {
                $msg = "User updated successfully!";
            } else {
                $error = "Failed to update user.";
            }
        }
    }
    elseif ($action === 'reset_password') {
        $id = $_POST['user_id'] ?? '';
        $new_pass = password_hash(trim($_POST['new_password'] ?? ''), PASSWORD_BCRYPT);

        if ($id && trim($_POST['new_password'] ?? '')) {
            $payload = ['password' => $new_pass];
            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq.$id", $SUPABASE_KEY, 'PATCH', $payload);
            if ($http >= 200 && $http < 300) {
                $msg = "Password reset successfully!";
            } else {
                $error = "Failed to reset password.";
            }
        }
    }
    elseif ($action === 'delete') {
        $id = $_POST['user_id'] ?? '';
        if ($id) {
            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq.$id", $SUPABASE_KEY, 'DELETE');
            if ($http >= 200 && $http < 300) {
                $msg = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Role Filter Fetch
$selected_role = $_GET['role'] ?? '';
$endpoint = rtrim($SUPABASE_URL, '/') . "/rest/v1/users?select=*";
if ($selected_role && $selected_role !== 'All') {
    $endpoint .= "&role=eq." . urlencode($selected_role);
}
$endpoint .= "&order=id.desc";

list($users, $http_code) = callSupabase($endpoint, $SUPABASE_KEY);
if (!is_array($users)) $users = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - SRMS Admin Panel</title>
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

/* Header Action Card */
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
    color: #1E3A8A;
    font-size: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.btn {
    padding: 10px 18px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13.5px;
    cursor: pointer;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}

.btn-back {
    background: #FFFFFF;
    color: #475569;
    border-color: #E2E8F0;
}
.btn-back:hover {
    background: #F8FAFC;
    color: #1E293B;
    border-color: #CBD5E1;
}

.btn-add {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.btn-add:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.btn-edit {
    background: #EFF6FF;
    color: #2563EB;
    border: 1px solid #BFDBFE;
    padding: 7px 11px;
    font-size: 12.5px;
    border-radius: 8px;
}
.btn-edit:hover {
    background: #2563EB;
    color: #FFFFFF;
}

.btn-pass {
    background: #FFFBEB;
    color: #D97706;
    border: 1px solid #FDE68A;
    padding: 7px 11px;
    font-size: 12.5px;
    border-radius: 8px;
}
.btn-pass:hover {
    background: #F59E0B;
    color: #FFFFFF;
}

.btn-del {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
    padding: 7px 11px;
    font-size: 12.5px;
    border-radius: 8px;
}
.btn-del:hover {
    background: #DC2626;
    color: #FFFFFF;
}

/* Filter Bar */
.filter-bar {
    background: #FFFFFF;
    padding: 16px 20px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.filter-bar select {
    padding: 8px 14px;
    border-radius: 8px;
    border: 1.5px solid #E2E8F0;
    background: #FFF;
    font-size: 13.5px;
    font-weight: 500;
    color: #1E293B;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-bar select:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    padding: 12px 18px;
    border-radius: 10px;
    border: 1px solid #A7F3D0;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-danger {
    background: #FEF2F2;
    color: #991B1B;
    padding: 12px 18px;
    border-radius: 10px;
    border: 1px solid #FECACA;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Table Card */
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

table.user-tbl {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

table.user-tbl th, table.user-tbl td {
    padding: 14px 18px;
    font-size: 13.5px;
    border-bottom: 1px solid #F1F5F9;
}

table.user-tbl th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
    border-bottom: 1.5px solid #E2E8F0;
}

table.user-tbl td {
    color: #334155;
}

table.user-tbl tr:hover td {
    background: #F8FAFC;
}

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-block;
}
.badge-admin, .badge-super\ admin { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }
.badge-faculty { background: #EDE9FE; color: #5B21B6; border: 1px solid #DDD6FE; }
.badge-hod { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
.badge-student { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
.badge-active { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: #FFFFFF;
    width: 100%;
    max-width: 480px;
    padding: 28px;
    border-radius: 18px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1.5px solid #F1F5F9;
    padding-bottom: 14px;
}

.modal-header h3 {
    color: #1E3A8A;
    font-size: 18px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}

.close-btn {
    background: #F1F5F9;
    border: none;
    font-size: 18px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    color: #64748B;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.close-btn:hover {
    background: #E2E8F0;
    color: #0F172A;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}

.form-group input, .form-group select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1.5px solid #E2E8F0;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
}

.form-group input:focus, .form-group select:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
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
        <span>Arignar Anna College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php" class="active"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
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
        <h1>Manage System Users</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($_SESSION['role']); ?>
        </div>
    </div>

    <div class="content-body">
        
        <!-- HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-users-gear" style="color: #2563EB;"></i> User Accounts</h2>
            <div class="header-actions">
                <a href="dashboard_admin.php" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openModal('addModal')" class="btn btn-add"><i class="fa-solid fa-user-plus"></i> Add New User</button>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <span><b>Total Users Found:</b> <span style="color:#2563EB; font-weight:800; font-size: 15px; margin-left: 4px;"><?=count($users)?></span></span>
            <form method="GET" style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:13px; font-weight:600; color:#475569;"><i class="fa-solid fa-filter"></i> Filter Role:</label>
                <select name="role" onchange="this.form.submit()">
                    <option value="All" <?=$selected_role=='All'?'selected':''?>>All Roles</option>
                    <option value="Super Admin" <?=$selected_role=='Super Admin'?'selected':''?>>Admin</option>
                    <option value="HOD" <?=$selected_role=='HOD'?'selected':''?>>HOD</option>
                    <option value="Faculty" <?=$selected_role=='Faculty'?'selected':''?>>Faculty</option>
                    <option value="Student" <?=$selected_role=='Student'?'selected':''?>>Student</option>
                </select>
            </form>
        </div>

        <!-- TABLE CARD -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="user-tbl">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th style="text-align: right; padding-right: 24px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($users)): 
                            $i = 1;
                            foreach($users as $u): 
                                $role_lower = strtolower($u['role'] ?? '');
                        ?>
                        <tr>
                            <td><?=$i++?></td>
                            <td><b><?=htmlspecialchars($u['name'] ?? '-')?></b></td>
                            <td><?=htmlspecialchars($u['email'] ?? '-')?></td>
                            <td><span class="badge badge-<?=$role_lower?>"><?=htmlspecialchars($u['role'] ?? '-')?></span></td>
                            <td><span class="badge badge-active"><?=htmlspecialchars($u['status'] ?? 'Active')?></span></td>
                            <td style="text-align: right; padding-right: 20px;">
                                <div style="display: inline-flex; gap: 6px; align-items: center;">
                                    <button onclick="openEditModal('<?=$u['id']?>', '<?=htmlspecialchars($u['name'], ENT_QUOTES)?>', '<?=htmlspecialchars($u['email'], ENT_QUOTES)?>', '<?=$u['role']?>')" class="btn-edit" title="Edit User"><i class="fa-solid fa-pen"></i></button>
                                    <button onclick="openPasswordModal('<?=$u['id']?>')" class="btn-pass" title="Reset Password"><i class="fa-solid fa-key"></i></button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?=$u['id']?>">
                                        <button type="submit" class="btn-del" title="Delete User"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:#64748B;">No users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- Add User Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> Add New User</h3>
            <button class="close-btn" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required placeholder="Enter full name">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="Enter email address">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter secure password">
            </div>
            <div class="form-group">
                <label>System Role</label>
                <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="HOD">HOD</option>
                    <option value="Faculty">Faculty</option>
                    <option value="Student">Student</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-back" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-add">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-pen"></i> Edit User Details</h3>
            <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            <div class="form-group">
                <label>System Role</label>
                <select name="role" id="edit_role" required>
                    <option value="Admin">Admin</option>
                    <option value="HOD">HOD</option>
                    <option value="Faculty">Faculty</option>
                    <option value="Student">Student</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-back" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-add">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-key"></i> Reset User Password</h3>
            <button class="close-btn" onclick="closeModal('passModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="pass_user_id">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-back" onclick="closeModal('passModal')">Cancel</button>
                <button type="submit" class="btn btn-pass" style="background:#F59E0B; color:#fff;">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
function openEditModal(id, name, email, role) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    openModal('editModal');
}
function openPasswordModal(id) {
    document.getElementById('pass_user_id').value = id;
    openModal('passModal');
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = "none";
    }
}
</script>

</body>
</html>