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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>College Announcements - Faculty Portal</title>
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
.content-body { padding: 30px 36px; display: grid; grid-template-columns: <?php echo $can_post ? '1fr 1.6fr' : '1fr'; ?>; gap: 26px; }
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
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 13.5px; outline: none; transition: border-color 0.2s; color: #0F172A; background: #FFFFFF; font-family: inherit; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.form-group textarea { height: 120px; resize: vertical; }

.btn-submit { width: 100%; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; border: none; padding: 12px; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }

/* Announcement Cards */
.anno-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 4px solid #2563EB; border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; position: relative; transition: box-shadow 0.2s; }
.anno-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.04); }
.anno-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.anno-title { font-size: 15px; font-weight: 700; color: #0F172A; }
.anno-badge { background: #EEF2FF; color: #4338CA; border: 1px solid #E0E7FF; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.anno-msg { font-size: 13px; color: #475569; line-height: 1.55; margin-bottom: 12px; }
.anno-footer { font-size: 11.5px; color: #64748B; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #F1F5F9; padding-top: 10px; }
.anno-footer i { color: #94A3B8; margin-right: 4px; }

.btn-del { color: #DC2626; text-decoration: none; font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #FEE2E2; transition: 0.2s; }
.btn-del:hover { background: #EF4444; color: white; }
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

            <li class="nav-category">Faculty Panel</li>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-envelope-open-text"></i> Student Leave Requests</a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-file-pdf"></i> Syllabus & Materials</a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-tasks"></i> Assignments</a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php" class="active"><i class="fa-solid fa-bullhorn"></i> Announcements</a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
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
            <h1>College Announcements</h1>
            <p>Publish departmental news, circulars, and notifications for staff and students</p>
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
        
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert-success">
                <i class="fa-solid fa-circle-check"></i> Announcement published successfully!
            </div>
        <?php endif; ?>

        <?php if ($can_post): ?>
        <!-- Post Announcement Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-bullhorn"></i>
                    <span>Post Announcement</span>
                </div>
            </div>
            <form action="announcements.php" method="POST">
                <input type="hidden" name="action" value="post_announcement">

                <div class="form-group">
                    <label for="title">Announcement Title</label>
                    <input type="text" id="title" name="title" placeholder="e.g. Model Exam Schedule" required>
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
                    <textarea id="message" name="message" placeholder="Type announcement details here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Publish Announcement
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Announcements Feed -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-newspaper"></i>
                    <span>Recent Announcements</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 500;">
                    Total: <?php echo count($announcements); ?> notices
                </span>
            </div>
            
            <?php if (empty($announcements)): ?>
                <div style="text-align: center; color: #94A3B8; padding: 40px 16px;">
                    <i class="fa-regular fa-bell-slash" style="font-size: 28px; display: block; margin-bottom: 8px; color: #CBD5E1;"></i>
                    No announcements posted yet.
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $item): ?>
                    <div class="anno-card">
                        <div class="anno-header">
                            <div class="anno-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <span class="anno-badge"><?php echo htmlspecialchars($item['target_audience'] ?? 'All'); ?></span>
                        </div>
                        <div class="anno-msg"><?php echo nl2br(htmlspecialchars($item['message'])); ?></div>
                        <div class="anno-footer">
                            <span><i class="fa-solid fa-user-pen"></i> <?php echo htmlspecialchars($item['posted_by']); ?></span>
                            <span>
                                <i class="fa-regular fa-clock"></i> <?php echo date('d M Y, h:i A', strtotime($item['created_at'])); ?>
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

</body>
</html>