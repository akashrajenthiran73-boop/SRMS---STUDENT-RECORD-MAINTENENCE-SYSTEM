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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & Support - Faculty Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #2563EB; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }

/* Main Content */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; letter-spacing: 0.3px; }

/* Page Layout */
.content-body { padding: 30px 36px; display: grid; grid-template-columns: <?php echo $is_admin ? '1fr' : '1fr 1.5fr'; ?>; gap: 26px; }
@media (max-width: 1024px) {
    .content-body { grid-template-columns: 1fr; }
}

.alert-success { grid-column: 1 / -1; background: #DCFCE7; border: 1px solid #BBF7D0; color: #15803D; padding: 14px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; display: flex; align-items: center; gap: 10px; margin-bottom: -10px; }

.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #F1F5F9; }
.card-title { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: #0F172A; }
.card-title i { color: #2563EB; }

/* Form Styles */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-group input, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 13.5px; outline: none; transition: border-color 0.2s; color: #0F172A; background: #FFFFFF; font-family: inherit; }
.form-group input:focus, .form-group textarea:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.form-group textarea { height: 110px; resize: vertical; }

.btn-submit { width: 100%; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; border: none; padding: 12px; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }

/* Ticket Card */
.ticket-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; transition: box-shadow 0.2s; }
.ticket-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.04); }
.ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.badge-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.badge-resolved { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }

.admin-reply-box { background: #F8FAFC; border-left: 3px solid #2563EB; padding: 12px 16px; border-radius: 8px; margin-top: 14px; font-size: 13px; }
</style>
</head>
<body>

<!-- Unified Faculty Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Faculty Panel</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> Students Record</a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> Bio Data</a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-database"></i> UMIS Data</a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> Result Analysis</a></li>

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> Circulars & Notices</a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> Events & Calendar</a></li>

            <li class="nav-category">Faculty Panel</li>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-envelope-open-text"></i> Student Leave Requests</a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-file-pdf"></i> Syllabus & Materials</a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-tasks"></i> Assignments</a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> Announcements</a></li>
            <li><a href="support.php" class="active"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="faculty_profile.php" class="sidebar-menu" style="display:flex; align-items:center; gap:12px; color:#94A3B8; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> Profile
        </a>
        <a href="../auth/logout.php" style="display:flex; align-items:center; gap:12px; color:#F87171; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Help & Support Center</h1>
            <p>Raise questions, system issues, or report marks discrepancies to administrators</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-tie"></i> <?php echo htmlspecialchars($role); ?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_email); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Your support query has been submitted successfully!
            </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'resolved'): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Ticket resolved and response sent!
            </div>
        <?php endif; ?>

        <?php if (!$is_admin): ?>
        <!-- STUDENT / FACULTY TICKET FORM -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-headset"></i>
                    <span>Submit Support Query</span>
                </div>
            </div>
            <form action="support.php" method="POST">
                <input type="hidden" name="action" value="create_ticket">

                <div class="form-group">
                    <label for="subject">Subject / Topic</label>
                    <input type="text" id="subject" name="subject" placeholder="e.g. Login Issue / Marks Correction" required>
                </div>

                <div class="form-group">
                    <label for="message">Describe Your Issue</label>
                    <textarea id="message" name="message" placeholder="Type detailed description here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- TICKETS LIST -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-list-check"></i>
                    <span><?php echo $is_admin ? 'All Support Tickets' : 'My Support Tickets'; ?></span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 500;">
                    Total: <?php echo count($tickets); ?> tickets
                </span>
            </div>
            
            <?php if (empty($tickets)): ?>
                <div style="text-align: center; color: #94A3B8; padding: 40px 16px;">
                    <i class="fa-regular fa-comments" style="font-size: 28px; display: block; margin-bottom: 8px; color: #CBD5E1;"></i>
                    No support tickets found.
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $t): ?>
                    <div class="ticket-card">
                        <div class="ticket-header">
                            <strong style="color: #0F172A; font-size: 15px;"><?php echo htmlspecialchars($t['subject']); ?></strong>
                            <span class="<?php echo ($t['status'] === 'Resolved') ? 'badge-resolved' : 'badge-pending'; ?>">
                                <i class="fa-solid <?php echo ($t['status'] === 'Resolved') ? 'fa-circle-check' : 'fa-hourglass-half'; ?>"></i>
                                <?php echo htmlspecialchars($t['status']); ?>
                            </span>
                        </div>

                        <p style="font-size: 13px; color: #475569; margin-bottom: 12px; line-height: 1.55;">
                            <?php echo nl2br(htmlspecialchars($t['message'])); ?>
                        </p>

                        <div style="font-size: 11.5px; color: #94A3B8; display: flex; justify-content: space-between;">
                            <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($t['user_email']); ?> (<?php echo htmlspecialchars($t['user_role']); ?>)</span>
                            <span><i class="fa-regular fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($t['created_at'])); ?></span>
                        </div>

                        <!-- Display Admin Reply if exists -->
                        <?php if (!empty($t['admin_reply'])): ?>
                            <div class="admin-reply-box">
                                <strong style="color: #2563EB;"><i class="fa-solid fa-reply"></i> Admin Response:</strong>
                                <p style="color: #334155; margin-top: 4px; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($t['admin_reply'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Admin Reply Form (For Admins to resolve) -->
                        <?php if ($is_admin && $t['status'] !== 'Resolved'): ?>
                            <form action="support.php" method="POST" style="margin-top: 15px; border-top: 1px dashed #E2E8F0; padding-top: 10px;">
                                <input type="hidden" name="action" value="resolve_ticket">
                                <input type="hidden" name="ticket_id" value="<?php echo $t['id']; ?>">
                                <div class="form-group" style="margin-bottom: 8px;">
                                    <input type="text" name="admin_reply" placeholder="Type solution/reply here..." required style="padding: 8px 12px; font-size: 12.5px;">
                                </div>
                                <button type="submit" class="btn-submit" style="padding: 6px 12px; font-size: 12px; width: auto; background: #16A34A;">
                                    <i class="fa-solid fa-check-double"></i> Reply & Resolve
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

</body>
</html>