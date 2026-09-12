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
$dashboard_url = 'dashboard_student.php';
if ($is_admin) {
    $dashboard_url = 'dashboard_admin.php';
} elseif ($role === 'Faculty' || $role === 'HOD') {
    $dashboard_url = 'dashboard_faculty.php';
}
?>
<?php
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & Support - HOD Portal</title>
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

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Support Layout */
.support-layout {
    display: grid;
    grid-template-columns: <?php echo $is_admin ? '1fr' : '380px 1fr'; ?>;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1024px) {
    .support-layout { grid-template-columns: 1fr; }
}

/* Cards */
.card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    padding: 24px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 14px;
    border-bottom: 1px solid #F1F5F9;
}

.card-title-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title-left i {
    color: #7C3AED;
}

/* Form Styles */
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

input[type="text"], textarea {
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

input[type="text"]:focus, textarea:focus {
    outline: none;
    border-color: #7C3AED;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
}

textarea {
    height: 120px;
    resize: vertical;
    line-height: 1.6;
}

.btn-submit {
    width: 100%;
    background: #7C3AED;
    color: #FFFFFF;
    padding: 12px 20px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    justify-content: center;
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

/* Ticket Cards */
.tickets-feed {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ticket-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-left: 4px solid #CBD5E1;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.ticket-card.status-resolved {
    border-left-color: #10B981;
}

.ticket-card.status-pending {
    border-left-color: #F59E0B;
}

.ticket-card:hover {
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
}

.ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    gap: 12px;
    flex-wrap: wrap;
}

.ticket-header strong {
    font-size: 15.5px;
    font-weight: 700;
    color: #0F172A;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-pending {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
}

.badge-resolved {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}

.ticket-message {
    font-size: 13.5px;
    color: #475569;
    line-height: 1.6;
    margin-bottom: 14px;
}

.ticket-meta {
    font-size: 12px;
    color: #64748B;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #F1F5F9;
    padding-top: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.ticket-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.ticket-meta i {
    color: #94A3B8;
}

.admin-reply-box {
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-left: 3px solid #7C3AED;
    padding: 12px 16px;
    border-radius: 10px;
    margin-top: 14px;
    font-size: 13px;
}

.admin-reply-box strong {
    color: #7C3AED;
    font-size: 12.5px;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.admin-reply-box p {
    color: #334155;
    line-height: 1.5;
}

.admin-reply-form {
    margin-top: 15px;
    border-top: 1px dashed #E2E8F0;
    padding-top: 14px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.admin-reply-form input {
    flex-grow: 1;
    padding: 9px 12px;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid #CBD5E1;
}

.btn-resolve {
    background: #10B981;
    color: #FFFFFF;
    border: none;
    padding: 9px 16px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    transition: background 0.2s;
}

.btn-resolve:hover {
    background: #059669;
}

.empty-state {
    text-align: center;
    padding: 48px 20px;
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

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
            <li><a href="support.php" class="active"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
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
            <h1>Help & Support Center</h1>
            <p>Direct assistance and query ticketing desk</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> <?=htmlspecialchars($role)?> Access
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
                    <i class="fa-solid fa-headset"></i>
                </div>
                <div>
                    <h1>Support & Technical Assistance</h1>
                    <p>Submit tickets for portal assistance, record disputes, or system adjustments</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Your query ticket has been submitted successfully! The administration team will review it.</span>
            </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'resolved'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Ticket response has been recorded and marked as resolved.</span>
            </div>
        <?php endif; ?>

        <div class="support-layout">
            
            <?php if (!$is_admin): ?>
            <!-- Submit Ticket Form -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Submit Support Query</span>
                    </div>
                </div>
                <form action="support.php" method="POST">
                    <input type="hidden" name="action" value="create_ticket">

                    <div class="form-group">
                        <label for="subject">Subject / Issue Topic</label>
                        <input type="text" id="subject" name="subject" placeholder="e.g. Department Bio-data Sync Issue" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Detailed Description</label>
                        <textarea id="message" name="message" placeholder="Provide full details of the inquiry or issue encountered..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Tickets List -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-list-check"></i>
                        <span><?php echo $is_admin ? 'All Support Tickets' : 'My Support Tickets'; ?></span>
                    </div>
                    <span style="font-size: 12px; font-weight: 700; color: #7C3AED; background: #F3E8FF; padding: 4px 10px; border-radius: 20px;">
                        <?=count($tickets)?> Total
                    </span>
                </div>
                
                <div class="tickets-feed">
                    <?php if (empty($tickets)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                            <h4 style="font-size: 15px; color: #0F172A; margin-bottom: 4px;">No support tickets found</h4>
                            <p style="font-size: 13px;">Submitted queries and administrator responses will be listed here.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): ?>
                            <?php $is_res = ($t['status'] === 'Resolved'); ?>
                            <div class="ticket-card <?=$is_res ? 'status-resolved' : 'status-pending'?>">
                                <div class="ticket-header">
                                    <strong><?php echo htmlspecialchars($t['subject']); ?></strong>
                                    <span class="badge-status <?php echo $is_res ? 'badge-resolved' : 'badge-pending'; ?>">
                                        <i class="fa-solid <?=$is_res ? 'fa-circle-check' : 'fa-clock'?>"></i>
                                        <?php echo htmlspecialchars($t['status']); ?>
                                    </span>
                                </div>

                                <p class="ticket-message">
                                    <?php echo nl2br(htmlspecialchars($t['message'])); ?>
                                </p>

                                <div class="ticket-meta">
                                    <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($t['user_email']); ?> (<?php echo htmlspecialchars($t['user_role']); ?>)</span>
                                    <span><i class="fa-solid fa-calendar-days"></i> <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></span>
                                </div>

                                <!-- Display Admin Reply if exists -->
                                <?php if (!empty($t['admin_reply'])): ?>
                                    <div class="admin-reply-box">
                                        <strong><i class="fa-solid fa-reply"></i> Admin Response:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- Admin Reply Form (For Admins to resolve) -->
                                <?php if ($is_admin && $t['status'] !== 'Resolved'): ?>
                                    <form action="support.php" method="POST" class="admin-reply-form">
                                        <input type="hidden" name="action" value="resolve_ticket">
                                        <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                        <input type="text" name="admin_reply" placeholder="Type resolution or instructions..." required>
                                        <button type="submit" class="btn-resolve">
                                            <i class="fa-solid fa-check-double"></i> Resolve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

</body>
</html>