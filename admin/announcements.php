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
<title>Announcements - Portal</title>
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
    grid-template-columns: <?php echo $can_post ? '380px 1fr' : '1fr'; ?>;
    gap: 28px;
    align-items: start;
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 28px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.card-title {
    color: #1E3A8A;
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 14px;
    border-bottom: 1.5px solid #F1F5F9;
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

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
    transition: all 0.2s ease;
    background: #FFFFFF;
}

.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.form-group textarea {
    height: 120px;
    resize: vertical;
}

.btn-submit {
    width: 100%;
    background: #2563EB;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.btn-submit:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
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

.anno-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-left: 4px solid #2563EB;
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

.anno-card:hover {
    border-color: #CBD5E1;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
}

.anno-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    gap: 10px;
}

.anno-title {
    font-size: 16px;
    font-weight: 700;
    color: #1E3A8A;
}

.anno-badge {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    white-space: nowrap;
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
    border-top: 1px dashed #E2E8F0;
    padding-top: 10px;
    flex-wrap: wrap;
    gap: 8px;
}

.btn-del {
    color: #DC2626;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    background: #FEF2F2;
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid #FECACA;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-del:hover {
    background: #DC2626;
    color: #FFFFFF;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; grid-template-columns: 1fr; }
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
            <li><a href="<?php echo $dashboard_url; ?>"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php" class="active"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
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

<div class="main-content">
    <div class="navbar">
        <h1>Announcements Portal</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($role); ?>: <?php echo htmlspecialchars($user_email); ?>
        </div>
    </div>

    <div class="content-body">
        
        <?php if ($can_post): ?>
        <!-- Post Announcement Form -->
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-bullhorn" style="color: #2563EB;"></i> Post Announcement</div>
            
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
                <div class="alert-success"><i class="fa-solid fa-circle-check"></i> Announcement published successfully!</div>
            <?php endif; ?>

            <form action="announcements.php" method="POST">
                <input type="hidden" name="action" value="post_announcement">

                <div class="form-group">
                    <label>Announcement Title</label>
                    <input type="text" name="title" placeholder="e.g. Model Exam Schedule" required>
                </div>

                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience">
                        <option value="All">All Students & Staff</option>
                        <option value="Students Only">Students Only</option>
                        <option value="Faculty Only">Faculty Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Message Content</label>
                    <textarea name="message" placeholder="Type announcement details here..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Publish Announcement
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Announcements Feed -->
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-newspaper" style="color: #2563EB;"></i> Recent Announcements</div>
            
            <?php if (empty($announcements)): ?>
                <p style="text-align: center; color: #94A3B8; padding: 40px;">No announcements posted yet.</p>
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
                                    &nbsp;|&nbsp; <a href="announcements.php?action=delete&id=<?php echo $item['id']; ?>" class="btn-del" onclick="return confirm('Delete this announcement?')"><i class="fa-solid fa-trash-can"></i> Delete</a>
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