<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'HOD'){
    header("Location: login.php"); 
    exit;
}

$hod_name = $_SESSION['name'] ?? 'HOD';
$msg = "";

// SEND NOTICE LOGIC
if(isset($_POST['send_notice'])){
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $send_to = $_POST['send_to']; // All or Pending

    if(!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/notices";
        $data = json_encode([
            'title' => $title,
            'message' => $message,
            'sent_to' => $send_to,
            'sent_by' => $hod_name
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                "apikey: $SUPABASE_KEY",
                "Authorization: Bearer $SUPABASE_KEY",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ]
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($http_code >= 200 && $http_code < 300){
            $msg = "success";
        } else {
            $msg = "error";
        }
    } else {
        $msg = "error";
    }
}

// FETCH NOTICES
$notices = [];
if(!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/notices?select=*&order=created_at.desc&limit=10";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_KEY", 
            "Authorization: Bearer $SUPABASE_KEY"
        ]
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($http_code == 200){
        $decoded = json_decode($response, true);
        if(is_array($decoded)) $notices = $decoded;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send Notice - HOD Portal</title>
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

.alert-error {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    color: #991B1B;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Card */
.card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    padding: 28px;
    margin-bottom: 30px;
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

/* Form Styles */
.form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

@media (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 7px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

input[type="text"], textarea, select {
    width: 100%;
    padding: 11px 14px;
    border-radius: 10px;
    border: 1px solid #CBD5E1;
    background: #FFFFFF;
    color: #0F172A;
    font-size: 13.5px;
    font-family: inherit;
    transition: all 0.2s ease;
}

input[type="text"]:focus, textarea:focus, select:focus {
    outline: none;
    border-color: #7C3AED;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

textarea {
    resize: vertical;
    min-height: 110px;
    line-height: 1.6;
}

.btn-submit {
    background: #7C3AED;
    color: #FFFFFF;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25);
}

.btn-submit:hover {
    background: #6D28D9;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.35);
}

/* Notice List Section */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
}

.section-title {
    color: #0F172A;
    font-size: 17px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: #7C3AED;
}

.notice-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.notice-item {
    background: #FFFFFF;
    padding: 22px 24px;
    border-radius: 14px;
    border: 1px solid #E2E8F0;
    border-left: 4px solid #7C3AED;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.notice-item:hover {
    border-color: #DDD6FE;
    border-left-color: #6D28D9;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
}

.notice-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.notice-item b {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
}

.target-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: #F1F5F9;
    color: #475569;
}

.target-badge.pending {
    background: #FEF3C7;
    color: #B45309;
}

.notice-item p {
    color: #475569;
    font-size: 13.5px;
    line-height: 1.6;
    margin-bottom: 14px;
}

.notice-meta {
    font-size: 12px;
    color: #64748B;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    border-top: 1px solid #F1F5F9;
    padding-top: 12px;
}

.notice-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.notice-meta i {
    color: #94A3B8;
}

.empty-state {
    text-align: center;
    padding: 48px 24px;
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px dashed #CBD5E1;
    color: #64748B;
}

.empty-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: #F1F5F9;
    color: #94A3B8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 12px;
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
            <li><a href="send_notice.php" class="active"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
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
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Broadcast Notices</h1>
            <p>Direct communication and instructions to students</p>
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

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <div>
                    <h1>Send Notice to Students</h1>
                    <p>Deliver urgent notices, deadline reminders, or circulars to your department students</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="dashboard_hod.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <?php if($msg=="success"): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Notice sent successfully! The broadcast has been logged and published.</span>
            </div>
        <?php elseif($msg=="error"): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>Failed to send notice. Please verify database connectivity or network settings.</span>
            </div>
        <?php endif; ?>

        <!-- Composer Card -->
        <div class="card">
            <div class="card-title">
                <i class="fa-solid fa-pen-nib"></i> Compose Notice
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">Notice Title</label>
                        <input type="text" id="title" name="title" placeholder="e.g. UMIS Verification Deadline Extension" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="send_to">Target Audience</label>
                        <select id="send_to" name="send_to" required>
                            <option value="All Students">All Students</option>
                            <option value="Pending Students">Pending Students Only</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="message">Notice Message</label>
                    <textarea id="message" name="message" rows="5" placeholder="Enter detailed notice message, instructions, deadlines, or links..." required></textarea>
                </div>
                
                <div style="margin-top: 24px;">
                    <button type="submit" name="send_notice" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Send Notice
                    </button>
                </div>
            </form>
        </div>

        <!-- Previously Sent Notices -->
        <div class="section-header">
            <h3 class="section-title">
                <i class="fa-solid fa-clock-rotate-left"></i> Previously Sent Notices
            </h3>
            <span style="font-size: 13px; color: #64748B; font-weight: 600;">
                Showing latest <?=count($notices)?> notices
            </span>
        </div>
        
        <div class="notice-list">
        <?php if(is_array($notices) && count($notices)>0): foreach($notices as $n): ?>
            <div class="notice-item">
                <div class="notice-item-header">
                    <b><?=htmlspecialchars($n['title'] ?? '-')?></b>
                    <?php 
                        $target = $n['send_to'] ?? 'All Students';
                        $is_pending = (stripos($target, 'Pending') !== false);
                    ?>
                    <span class="target-badge <?=$is_pending ? 'pending' : ''?>">
                        <i class="fa-solid <?= $is_pending ? 'fa-hourglass-half' : 'fa-users' ?>"></i>
                        <?=htmlspecialchars($target)?>
                    </span>
                </div>
                <p><?=nl2br(htmlspecialchars($n['message'] ?? '-'))?></p>
                <div class="notice-meta">
                    <span><i class="fa-solid fa-user-tie"></i> Sent By: <strong><?=htmlspecialchars($n['sent_by'] ?? '-')?></strong></span>
                    <span><i class="fa-solid fa-calendar-days"></i> Sent On: <?=isset($n['created_at']) ? date('d M Y, h:i A', strtotime($n['created_at'])) : '-'?></span>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                <h4 style="font-size: 15px; color: #0F172A; margin-bottom: 4px;">No notices sent yet</h4>
                <p style="font-size: 13px;">Notices and circulars you broadcast will appear here chronologically.</p>
            </div>
        <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>