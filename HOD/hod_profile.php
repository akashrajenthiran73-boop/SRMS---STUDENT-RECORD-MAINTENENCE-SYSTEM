<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Safe Session & HOD Role Check
$session_user_id = $_SESSION['user_id'] ?? '';
$session_role = trim($_SESSION['role'] ?? '');

$is_hod = (strcasecmp($session_role, 'HOD') === 0 || strcasecmp($session_role, 'Head of Department') === 0);

if (empty($session_user_id) || !$is_hod) {
    header("Location: ../auth/login.php");
    exit;
}

// 1. Safe Multi-location .env File Loader
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

function callSupabase($url, $key, $method = 'GET', $data = []) {
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
    }

    curl_setopt_array($ch, $options);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($res, true), $http];
}

$msg = '';
$error = '';

// Handle Form Actions using user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Editable Profile Info (Phone, Address, Qualification, Experience)
    if ($action === 'update_profile') {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');
        $experience = trim($_POST['experience'] ?? '');

        $payload = [
            'phone' => $phone,
            'address' => $address,
            'qualification' => $qualification,
            'experience' => $experience
        ];
        list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
        
        if ($http >= 200 && $http < 300) {
            $msg = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    } 
    // 2. Change Password
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            list($users, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=password", $SUPABASE_KEY, 'GET');
            
            if (!empty($users) && password_verify($current_password, $users[0]['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $payload = ['password' => $hashed_password];
                
                list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
                
                if ($http >= 200 && $http < 300) {
                    $msg = "Password changed successfully!";
                } else {
                    $error = "Failed to update password.";
                }
            } else {
                $error = "Incorrect current password.";
            }
        }
    }
    // 3. Photo Upload Handling
    elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'hod_' . time() . '.' . $fileExtension;
                $uploadFileDir = '../uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $dest_path = $uploadFileDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $payload = ['photo' => $newFileName];
                    list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
                    if ($http >= 200 && $http < 300) {
                        $msg = "Profile photo uploaded successfully!";
                    } else {
                        $error = "Failed to update photo path in database.";
                    }
                } else {
                    $error = "Error moving the uploaded file.";
                }
            } else {
                $error = "Invalid file type. Only JPG, JPEG, PNG, WEBP allowed.";
            }
        } else {
            $error = "Please choose a valid image file.";
        }
    }
}

// Fetch HOD Details from Supabase
$hod = [];
if (!empty($session_user_id)) {
    list($users, $http_code) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=*", $SUPABASE_KEY, 'GET');
    $hod = (!empty($users)) ? $users[0] : [];
}

$dept_name = $hod['department'] ?? '';

// Calculate Department Stats (No of Faculty and No of Students in this department)
$faculty_count = 0;
$student_count = 0;

if (!empty($dept_name)) {
    // Fetch faculty under this department
    list($faculty_list, $f_http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?department=eq." . urlencode($dept_name) . "&role=eq.Faculty&select=id", $SUPABASE_KEY, 'GET');
    if ($f_http >= 200 && $f_http < 300 && is_array($faculty_list)) {
        $faculty_count = count($faculty_list);
    }

    // Fetch students under this department
    list($student_list, $s_http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/students?department=eq." . urlencode($dept_name) . "&select=id", $SUPABASE_KEY, 'GET');
    if ($s_http >= 200 && $s_http < 300 && is_array($student_list)) {
        $student_count = count($student_list);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HOD Profile - SRMS Portal</title>
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
.sidebar-footer a.active { background: #7C3AED; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35); }

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header */
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
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.btn-back {
    background: #F8FAFC;
    color: #475569;
    border: 1px solid #CBD5E1;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s ease;
}

.btn-back:hover {
    background: #F1F5F9;
    color: #0F172A;
}

/* Alert Boxes */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: fadeIn 0.25s ease-out;
}

.alert-success {
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
}

.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    color: #991B1B;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Profile Grid */
.profile-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1024px) {
    .profile-grid { grid-template-columns: 1fr; }
}

/* Cards */
.card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    padding: 24px;
    margin-bottom: 24px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
}

.card-title i {
    color: #7C3AED;
}

/* Avatar Card */
.profile-avatar-card {
    text-align: center;
}

.avatar-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 16px;
    border-radius: 50%;
    padding: 4px;
    background: linear-gradient(135deg, #7C3AED, #6D28D9);
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.2);
}

.avatar-inner {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    overflow: hidden;
    background: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-inner i {
    font-size: 48px;
    color: #94A3B8;
}

.profile-avatar-card h3 {
    font-size: 18px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 4px;
}

.profile-avatar-card p {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 12px;
}

.designation-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    background: #F3E8FF;
    color: #7C3AED;
    border: 1px solid #E9D5FF;
    margin-bottom: 20px;
}

.upload-box {
    background: #F8FAFC;
    border: 1px dashed #CBD5E1;
    border-radius: 12px;
    padding: 16px;
    margin-top: 14px;
    text-align: left;
}

.upload-box label {
    font-size: 11.5px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    display: block;
    margin-bottom: 8px;
}

.upload-box input[type="file"] {
    width: 100%;
    font-size: 12px;
    color: #64748B;
    margin-bottom: 10px;
}

/* Stat Tiles */
.stats-tiles {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.stat-tile {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    text-align: center;
    transition: all 0.2s ease;
}

.stat-tile:hover {
    border-color: #DDD6FE;
    background: #FFFFFF;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.08);
}

.stat-tile h5 {
    font-size: 26px;
    font-weight: 800;
    color: #7C3AED;
    margin-bottom: 4px;
}

.stat-tile span {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748B;
    line-height: 1.3;
    display: block;
}

/* Form Layout */
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 640px) {
    .form-grid { grid-template-columns: 1fr; }
}

.form-group {
    margin-bottom: 14px;
}

.form-group.full {
    grid-column: span 2;
}

@media (max-width: 640px) {
    .form-group.full { grid-column: span 1; }
}

label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

input[type="text"], input[type="email"], input[type="password"] {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #0F172A;
    font-size: 13.5px;
    font-family: inherit;
    transition: all 0.2s ease;
}

input:focus {
    outline: none;
    border-color: #7C3AED;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

.btn-primary {
    background: #7C3AED;
    color: #FFFFFF;
    padding: 11px 22px;
    border: none;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
}

.btn-primary:hover {
    background: #6D28D9;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.35);
}

.btn-upload {
    width: 100%;
    justify-content: center;
    padding: 8px 14px;
    font-size: 12px;
}

@media(max-width: 900px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
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
            <li><a href="bio_data_list.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
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
        <a href="hod_profile.php" class="active" style="color:#FFFFFF;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>Profile</span>
        </a>
        <a href="../auth/logout.php" style="color:#F87171;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>HOD Profile & Account</h1>
            <p>Personal profile, credentials, and departmental overview</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> HOD Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod['name'] ?? 'HOD Faculty')?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <h1>Executive Profile</h1>
                    <p>Department: <strong><?=htmlspecialchars($dept_name ?: 'Computer Science')?></strong></p>
                </div>
            </div>
            <div>
                <a href="dashboard_hod.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=$error?></div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <!-- Left Column: Avatar & Quick Overview -->
            <div>
                <div class="card profile-avatar-card">
                    <div class="avatar-wrapper">
                        <div class="avatar-inner">
                            <?php if(!empty($hod['photo'])): ?>
                                <img src="../uploads/<?=htmlspecialchars($hod['photo'])?>" alt="HOD Photo">
                            <?php else: ?>
                                <i class="fa-solid fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <h3><?=htmlspecialchars($hod['name'] ?? 'HOD Faculty')?></h3>
                    <p><?=htmlspecialchars($hod['email'] ?? '')?></p>
                    <span class="designation-badge">
                        <i class="fa-solid fa-crown"></i> Head of Department
                    </span>

                    <!-- Photo Upload Form -->
                    <div class="upload-box">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_photo">
                            <label for="photo">Update Profile Photo</label>
                            <input type="file" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp" required>
                            <button type="submit" class="btn-primary btn-upload">
                                <i class="fa-solid fa-upload"></i> Upload Photo
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Department Statistics -->
                <div class="card">
                    <div class="card-title">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Department Scope</span>
                    </div>
                    <div class="stats-tiles">
                        <div class="stat-tile">
                            <h5><?=$faculty_count?></h5>
                            <span>Faculty Members</span>
                        </div>
                        <div class="stat-tile">
                            <h5><?=$student_count?></h5>
                            <span>Active Students</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Profile Form & Password Security -->
            <div>
                <!-- 1. Profile Information -->
                <div class="card">
                    <div class="card-title">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Profile & Work Details</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?=htmlspecialchars($hod['name'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label for="employee_id">Employee / Staff ID</label>
                                <input type="text" id="employee_id" name="employee_id" value="<?=htmlspecialchars($hod['employee_id'] ?? ($hod['id'] ?? ''))?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?=htmlspecialchars($hod['email'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" value="<?=htmlspecialchars($hod['phone'] ?? '')?>" placeholder="+91 98765 43210">
                            </div>
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?=htmlspecialchars($hod['department'] ?? '')?>" placeholder="Computer Science">
                            </div>
                            <div class="form-group">
                                <label for="designation">Designation</label>
                                <input type="text" id="designation" name="designation" value="<?=htmlspecialchars($hod['designation'] ?? 'HOD')?>" required>
                            </div>
                            <div class="form-group">
                                <label for="joining_date">Joining Date</label>
                                <input type="text" id="joining_date" name="joining_date" value="<?=htmlspecialchars($hod['joining_date'] ?? '')?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input type="text" id="dob" name="dob" value="<?=htmlspecialchars($hod['dob'] ?? '')?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="form-group">
                                <label for="qualification">Academic Qualification</label>
                                <input type="text" id="qualification" name="qualification" value="<?=htmlspecialchars($hod['qualification'] ?? '')?>" placeholder="e.g. Ph.D, M.Tech, MCA">
                            </div>
                            <div class="form-group">
                                <label for="experience">Experience (Years)</label>
                                <input type="text" id="experience" name="experience" value="<?=htmlspecialchars($hod['experience'] ?? '')?>" placeholder="e.g. 12 Years">
                            </div>
                            <div class="form-group full">
                                <label for="address">Residential Address</label>
                                <input type="text" id="address" name="address" value="<?=htmlspecialchars($hod['address'] ?? '')?>" placeholder="Door No, Street Name, City, Pincode">
                            </div>
                        </div>
                        <div style="margin-top: 18px;">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 2. Security / Password Update -->
                <div class="card">
                    <div class="card-title">
                        <i class="fa-solid fa-lock"></i>
                        <span>Security & Password</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Enter current account password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Enter new strong password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                            </div>
                        </div>
                        <div style="margin-top: 18px;">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-key"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>

    </div>
</div>

</body>
</html>