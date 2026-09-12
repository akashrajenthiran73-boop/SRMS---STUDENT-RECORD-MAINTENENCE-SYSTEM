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

// Check if user can post announcements
$can_post = in_array($role, ['Admin', 'Super Admin', 'HOD', 'Faculty']);

// Supabase Credentials
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Handle Post Action (Admin / Faculty / HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_announcement') {
    if (!$can_post) {
        die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
    }

    $title           = trim($_POST['title'] ?? '');
    $target_audience = trim($_POST['target_audience'] ?? 'All');
    $message         = trim($_POST['message'] ?? '');

    $payload = [
        'title'           => $title,
        'target_audience' => $target_audience,
        'message'         => $message,
        'posted_by'       => $user_email . " (" . $role . ")"
    ];

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements";
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

    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code == 200 || $code == 201) {
        header("Location: announcements.php?msg=success");
        exit();
    } else {
        echo "Database Error: " . $res;
        exit();
    }
}

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && $can_post) {
    $del_id = $_GET['id'] ?? '';
    if (!empty($del_id)) {
        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements?id=eq." . urlencode($del_id);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    header("Location: announcements.php");
    exit();
}

// Fetch Announcements from Supabase
$announcements = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/announcements?select=*&order=id.desc";
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
    $announcements = json_decode($res, true) ?? [];
}

// Dashboard URL based on Role
$dashboard_url = 'dashboard_student.php';
if (in_array($role, ['Admin', 'Super Admin'])) {
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
<title>Announcements - HOD Portal</title>
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

/* Grid Layout */
.announcements-layout {
    display: grid;
    grid-template-columns: <?php echo $can_post ? '380px 1fr' : '1fr'; ?>;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1024px) {
    .announcements-layout { grid-template-columns: 1fr; }
}

/* Card */
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

input[type="text"], select, textarea {
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

input[type="text"]:focus, select:focus, textarea:focus {
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

/* Announcement Cards */
.anno-feed {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.anno-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-left: 4px solid #7C3AED;
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.anno-card:hover {
    border-color: #DDD6FE;
    border-left-color: #6D28D9;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
}

.anno-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    gap: 12px;
    flex-wrap: wrap;
}

.anno-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
}

.anno-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    background: #F3E8FF;
    color: #7C3AED;
    border: 1px solid #E9D5FF;
}

.anno-badge.all {
    background: #EFF6FF;
    color: #2563EB;
    border-color: #DBEAFE;
}

.anno-msg {
    font-size: 13.5px;
    color: #475569;
    line-height: 1.6;
    margin-bottom: 14px;
}

.anno-footer {
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

.anno-footer span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.anno-footer i {
    color: #94A3B8;
}

.btn-del {
    color: #EF4444;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.btn-del:hover {
    background: #FEF2F2;
    color: #DC2626;
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
            <li><a href="announcements.php" class="active"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
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
            <h1>Announcements Board</h1>
            <p>Publish departmental notices and institutional circulars</p>
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
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <div>
                    <h1>Department Announcements</h1>
                    <p>Broadcast critical updates to faculty members and students across semesters</p>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span>Announcement published successfully and pinned to the departmental board!</span>
            </div>
        <?php endif; ?>

        <div class="announcements-layout">
            
            <?php if ($can_post): ?>
            <!-- Post Announcement Form -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Create Announcement</span>
                    </div>
                </div>
                <form action="announcements.php" method="POST">
                    <input type="hidden" name="action" value="post_announcement">

                    <div class="form-group">
                        <label for="title">Announcement Title</label>
                        <input type="text" id="title" name="title" placeholder="e.g. Model Exam Schedule Released" required>
                    </div>

                    <div class="form-group">
                        <label for="target_audience">Target Audience</label>
                        <select id="target_audience" name="target_audience">
                            <option value="All">All Students & Staff</option>
                            <option value="Students Only">Students Only</option>
                            <option value="Faculty Only">Faculty Only</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Message Content</label>
                        <textarea id="message" name="message" placeholder="Type announcement details, schedules, venue, or links here..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Publish Announcement
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- Announcements Feed -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-newspaper"></i>
                        <span>Recent Announcements</span>
                    </div>
                    <span style="font-size: 12px; font-weight: 700; color: #7C3AED; background: #F3E8FF; padding: 4px 10px; border-radius: 20px;">
                        <?=count($announcements)?> Active
                    </span>
                </div>
                
                <div class="anno-feed">
                    <?php if (empty($announcements)): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                            <h4 style="font-size: 15px; color: #0F172A; margin-bottom: 4px;">No announcements posted yet</h4>
                            <p style="font-size: 13px;">Newly published notices will be showcased here for all members.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($announcements as $item): ?>
                            <div class="anno-card">
                                <div class="anno-header">
                                    <div class="anno-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <?php 
                                        $target = $item['target_audience'] ?? 'All';
                                        $is_all = (strcasecmp($target, 'All') === 0 || stripos($target, 'All') !== false);
                                    ?>
                                    <span class="anno-badge <?=$is_all ? 'all' : ''?>">
                                        <i class="fa-solid <?=$is_all ? 'fa-globe' : 'fa-users'?>"></i>
                                        <?php echo htmlspecialchars($target); ?>
                                    </span>
                                </div>
                                <div class="anno-msg"><?php echo nl2br(htmlspecialchars($item['message'])); ?></div>
                                <div class="anno-footer">
                                    <span><i class="fa-solid fa-user-pen"></i> <?php echo htmlspecialchars($item['posted_by']); ?></span>
                                    <span>
                                        <i class="fa-solid fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?>
                                        <?php if ($can_post): ?>
                                            &nbsp;|&nbsp; <a href="announcements.php?action=delete&id=<?php echo $item['id']; ?>" class="btn-del" onclick="return confirm('Delete this announcement?')"><i class="fa-solid fa-trash"></i> Delete</a>
                                        <?php endif; ?>
                                    </span>
                                </div>
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