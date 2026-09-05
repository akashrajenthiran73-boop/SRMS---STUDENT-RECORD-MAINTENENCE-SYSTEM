<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$is_admin = in_array($role, ['Admin', 'Super Admin']);

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// --- 1. CREATE TICKET (Students / Faculty) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $payload = [
        'user_email' => $user_email,
        'user_role'  => $role,
        'subject'    => $subject,
        'message'    => $message,
        'status'     => 'Pending'
    ];

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/support_tickets";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    curl_exec($ch);
    curl_close($ch);

    header("Location: support.php?msg=submitted");
    exit();
}

// --- 2. ADMIN RESOLVE TICKET ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_ticket' && $is_admin) {
    $ticket_id   = $_POST['ticket_id'] ?? '';
    $admin_reply = trim($_POST['admin_reply'] ?? '');

    if (!empty($ticket_id)) {
        $payload = [
            'admin_reply' => $admin_reply,
            'status'      => 'Resolved'
        ];

        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/support_tickets?id=eq." . urlencode($ticket_id);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json"
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    header("Location: support.php?msg=resolved");
    exit();
}

// --- 3. FETCH TICKETS ---
$tickets = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    // Admin sees all tickets, users see only their tickets
    $filter = $is_admin ? "" : "?user_email=eq." . urlencode($user_email);
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/support_tickets" . $filter . ($is_admin ? "?order=id.desc" : "&order=id.desc");

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
    $tickets = json_decode($res, true) ?? [];
}

// Sidebar Dashboard Routing
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User';
$is_student = ($role === 'Student');

$dashboard_url = 'dashboard_student.php';
if ($is_admin) {
    $dashboard_url = '../admin/dashboard_admin.php';
} elseif ($role === 'Faculty' || $role === 'HOD') {
    $dashboard_url = ($role === 'HOD') ? '../HOD/dashboard_hod.php' : '../faculty/dashboard_faculty.php';
}

// Metrics
$total_tickets = count($tickets);
$resolved_count = count(array_filter($tickets, fn($t) => ($t['status'] ?? '') === 'Resolved'));
$pending_count = $total_tickets - $resolved_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & Support Center - SRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

:root {
    --primary: #059669;
    --primary-dark: #047857;
    --primary-light: #ECFDF5;
    --primary-border: #A7F3D0;
    --sidebar-bg: #0B132B;
    --bg-light: #F8FAFC;
    --card-bg: #FFFFFF;
    --border-color: #E2E8F0;
    --text-dark: #0F172A;
    --text-muted: #64748B;
    --accent-blue: #0284C7;
    --accent-amber: #D97706;
}

body {
    background-color: var(--bg-light);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    color: #FFFFFF;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 100;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
}

.sidebar-top {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px;
}

.logo-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-brand h2 {
    font-size: 17px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.2px;
}

.sidebar-brand span {
    font-size: 11px;
    color: #94A3B8;
    display: block;
    font-weight: 500;
}

.sidebar-menu {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 8px;
    max-height: calc(100vh - 170px);
    overflow-y: auto;
    padding-right: 4px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 9px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}

.sidebar-menu li a.active {
    background: var(--primary);
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-menu li a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.nav-category {
    font-size: 10.5px;
    text-transform: uppercase;
    color: #64748B;
    font-weight: 700;
    letter-spacing: 0.8px;
    padding: 12px 14px 4px;
}

.sidebar-footer {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-footer a:hover {
    background: rgba(255, 255, 255, 0.06);
}

/* Main Content */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 90;
}

.topbar-title h1 {
    font-size: 19px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.topbar-title p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-badge {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--primary-border);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.content-body {
    padding: 30px 40px 60px;
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Notifications */
.alert-box {
    padding: 14px 20px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #ECFDF5;
    border: 1px solid #A7F3D0;
    color: #065F46;
}

/* Stats Row */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-info h4 {
    font-size: 11.5px;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-info h2 {
    font-size: 22px;
    color: var(--text-dark);
    font-weight: 800;
    margin-top: 2px;
}

/* Layout Grid */
.layout-grid {
    display: grid;
    grid-template-columns: <?php echo $is_admin ? '1fr' : '380px 1fr'; ?>;
    gap: 24px;
    align-items: start;
}

/* Card */
.card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Form */
.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 13.5px;
    color: var(--text-dark);
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s ease;
}

.form-control:focus {
    background: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

textarea.form-control {
    height: 120px;
    resize: vertical;
}

.btn-submit {
    width: 100%;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #FFFFFF;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.btn-submit:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
}

/* Tickets Feed */
.feed-controls {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    min-width: 220px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
}

.search-box input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.search-box input:focus {
    background: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

.filter-pills {
    display: flex;
    gap: 6px;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 10px;
}

.filter-pill {
    padding: 6px 12px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-pill.active {
    background: #FFFFFF;
    color: var(--text-dark);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.tickets-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
}

.ticket-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px 22px;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.ticket-card:hover {
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
    border-color: #CBD5E1;
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 10px;
}

.ticket-subject {
    font-size: 15.5px;
    font-weight: 700;
    color: var(--text-dark);
}

.badge-status {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}

.badge-pending {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
}

.badge-resolved {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
}

.ticket-msg {
    font-size: 13.5px;
    color: #334155;
    line-height: 1.6;
    margin-bottom: 14px;
}

.ticket-meta {
    font-size: 12px;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px dashed var(--border-color);
    padding-top: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.admin-reply-box {
    background: #F0FDF4;
    border-left: 3px solid var(--primary);
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 14px;
}

.admin-reply-title {
    font-size: 12.5px;
    font-weight: 700;
    color: var(--primary-dark);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.admin-reply-content {
    font-size: 13px;
    color: #1E293B;
    line-height: 1.5;
}

.admin-action-form {
    margin-top: 14px;
    border-top: 1px dashed var(--border-color);
    padding-top: 12px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    color: #CBD5E1;
    margin-bottom: 12px;
}

.empty-state h3 {
    font-size: 16px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
}

.empty-state p {
    font-size: 13px;
}

/* Responsive */
@media (max-width: 1024px) {
    .layout-grid { grid-template-columns: 1fr; }
    .stats-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Student Administration</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <?php if ($is_admin): ?>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
                <li><a href="support.php" class="active"><i class="fa-solid fa-circle-question"></i> <span>Support Desk</span></a></li>
                <li class="nav-category">Portal Navigation</li>
                <li><a href="<?php echo htmlspecialchars($dashboard_url); ?>"><i class="fa-solid fa-arrow-left"></i> <span>Back to Admin Panel</span></a></li>
            <?php else: ?>
                <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
                <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
                <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
                <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
                <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
                <li><a href="marks.php"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

                <li class="nav-category">Academic & Services</li>
                <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
                <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
                <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
                <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
                <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
                <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
                <li><a href="support.php" class="active"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="sidebar-footer">
        <?php if ($is_student): ?>
            <a href="student_profile.php" style="color:#94A3B8;">
                <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>My Profile</span>
            </a>
        <?php endif; ?>
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
            <h1>Helpdesk & Support Center</h1>
            <p>Direct academic assistance, ticketing portal, and administrative inquiries</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-headset"></i> <?php echo htmlspecialchars($role); ?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-circle-question"></i>
                </div>
                <div>
                    <h1>Support Ticket Tracking</h1>
                    <p>Account: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>
            <div class="alert-box alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Your inquiry ticket has been submitted to the support desk. Our team will review and respond shortly!</span>
            </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'resolved'): ?>
            <div class="alert-box alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Support ticket has been successfully updated with response and resolved!</span>
            </div>
        <?php endif; ?>

        <!-- Stat Tiles -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #E0F2FE; color: #0284C7;">
                    <i class="fa-solid fa-ticket"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Inquiries</h4>
                    <h2><?php echo $total_tickets; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #059669;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="stat-info">
                    <h4>Resolved Tickets</h4>
                    <h2><?php echo $resolved_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                    <i class="fa-regular fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h4>Pending Queries</h4>
                    <h2><?php echo $pending_count; ?></h2>
                </div>
            </div>
        </div>

        <!-- Layout Grid -->
        <div class="layout-grid">
            
            <?php if (!$is_admin): ?>
            <!-- Ticket Composer Form -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i>
                        <span>Submit Support Inquiry</span>
                    </div>
                </div>
                <form action="support.php" method="POST">
                    <input type="hidden" name="action" value="create_ticket">

                    <div class="form-group">
                        <label>Subject / Topic</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. Portal Login or Marksheet Query" required>
                    </div>

                    <div class="form-group">
                        <label>Issue Description</label>
                        <textarea name="message" class="form-control" placeholder="Explain your inquiry or issue in detail..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Tickets List -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fa-solid fa-list-check" style="color: var(--primary);"></i>
                        <span><?php echo $is_admin ? 'All Support Inquiries' : 'My Ticket History'; ?></span>
                    </div>
                    <div class="feed-controls">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="searchInput" placeholder="Search inquiries..." onkeyup="filterTickets()">
                        </div>
                        <div class="filter-pills">
                            <button class="filter-pill active" onclick="setStatusFilter('all', this)">All</button>
                            <button class="filter-pill" onclick="setStatusFilter('pending', this)">Pending</button>
                            <button class="filter-pill" onclick="setStatusFilter('resolved', this)">Resolved</button>
                        </div>
                    </div>
                </div>

                <?php if (empty($tickets)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-comment-dots"></i>
                        <h3>No Support Tickets Recorded</h3>
                        <p>Any questions or requests submitted to administration will be tracked here.</p>
                    </div>
                <?php else: ?>
                    <div class="tickets-list" id="ticketsContainer">
                        <?php foreach ($tickets as $t): 
                            $status = $t['status'] ?? 'Pending';
                            $is_res = ($status === 'Resolved');
                        ?>
                            <div class="ticket-card" data-status="<?php echo strtolower($status); ?>">
                                <div class="ticket-header">
                                    <div class="ticket-subject"><?php echo htmlspecialchars($t['subject']); ?></div>
                                    <span class="badge-status <?php echo $is_res ? 'badge-resolved' : 'badge-pending'; ?>">
                                        <i class="<?php echo $is_res ? 'fa-solid fa-check' : 'fa-regular fa-clock'; ?>"></i>
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </div>

                                <div class="ticket-msg"><?php echo nl2br(htmlspecialchars($t['message'])); ?></div>

                                <div class="ticket-meta">
                                    <span>
                                        <i class="fa-solid fa-user" style="color: #94A3B8; margin-right: 4px;"></i>
                                        <?php echo htmlspecialchars($t['user_email']); ?> (<?php echo htmlspecialchars($t['user_role'] ?? 'Student'); ?>)
                                    </span>
                                    <span>
                                        <i class="fa-regular fa-calendar-days" style="margin-right: 4px;"></i>
                                        <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?>
                                    </span>
                                </div>

                                <?php if (!empty($t['admin_reply'])): ?>
                                    <div class="admin-reply-box">
                                        <div class="admin-reply-title">
                                            <i class="fa-solid fa-reply"></i> Administration Response
                                        </div>
                                        <div class="admin-reply-content"><?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_admin && !$is_res): ?>
                                    <form action="support.php" method="POST" class="admin-action-form">
                                        <input type="hidden" name="action" value="resolve_ticket">
                                        <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($t['id']); ?>">
                                        <input type="text" name="admin_reply" class="form-control" placeholder="Provide solution and administrative resolution..." required style="padding: 8px 12px; font-size: 13px;">
                                        <button type="submit" class="btn-submit" style="width: auto; padding: 8px 16px; font-size: 12.5px; white-space: nowrap; background: #059669;">
                                            <i class="fa-solid fa-check-double"></i> Resolve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </div>
</div>

<script>
let currentStatus = 'all';

function setStatusFilter(status, btn) {
    currentStatus = status;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    filterTickets();
}

function filterTickets() {
    var query = document.getElementById('searchInput').value.toLowerCase();
    var cards = document.querySelectorAll('#ticketsContainer .ticket-card');

    cards.forEach(function(card) {
        var cardText = card.innerText.toLowerCase();
        var cardStatus = card.getAttribute('data-status') || '';
        
        var matchesQuery = cardText.includes(query);
        var matchesStatus = (currentStatus === 'all') || (cardStatus === currentStatus);

        if (matchesQuery && matchesStatus) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>

</body>
</html>