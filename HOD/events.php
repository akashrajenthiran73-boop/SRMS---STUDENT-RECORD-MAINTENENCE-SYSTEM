<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOD') {
    header("Location: ../auth/login.php");
    exit();
}

$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? 'HOD Faculty';
require_once __DIR__ . '/../includes/college_data.php';

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Symposium';
    $event_date = $_POST['event_date'] ?? date('Y-m-d');
    $time = trim($_POST['time'] ?? '10:00 AM');
    $venue = trim($_POST['venue'] ?? 'CS Lab / Auditorium');
    $description = trim($_POST['description'] ?? '');

    if (empty($title)) {
        $error = 'Event title is required!';
    } else {
        $saved = save_event([
            'title' => $title,
            'category' => $category,
            'department' => 'CS',
            'event_date' => $event_date,
            'time' => $time,
            'venue' => $venue,
            'coordinator' => $hod_name . ' (HOD CS)',
            'description' => $description
        ]);

        if ($saved) {
            $msg = 'CS Department event scheduled successfully!';
        } else {
            $error = 'Failed to schedule event.';
        }
    }
}

$events = get_college_events('CS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Events - HOD Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

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

.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

.content-body { padding: 30px 36px; }

.page-header-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}
.page-header-card h2 { color: #0F172A; font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 10px; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 16px;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
    border: none;
}
.btn-primary { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; box-shadow: 0 4px 12px rgba(124, 58, 237, 0.25); }
.btn-primary:hover { opacity: 0.95; transform: translateY(-1px); }
.btn-light { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }

.events-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
.event-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease;
}
.event-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.06); }
.event-date-badge { background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; padding: 5px 12px; border-radius: 8px; font-weight: 800; font-size: 12.5px; }
.event-title { font-size: 15px; font-weight: 800; color: #0F172A; margin: 12px 0 6px 0; }
.event-desc { font-size: 12.5px; color: #64748B; line-height: 1.5; margin-bottom: 14px; }
.event-meta { border-top: 1px solid #F1F5F9; padding-top: 12px; font-size: 12px; color: #475569; display: flex; flex-direction: column; gap: 4px; }

.alert-success { background: #ECFDF5; color: #065F46; padding: 12px 18px; border-radius: 10px; border: 1px solid #A7F3D0; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; }
.alert-error { background: #FEF2F2; color: #991B1B; padding: 12px 18px; border-radius: 10px; border: 1px solid #FECACA; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; }

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 200;
}
.modal-box {
    background: #FFFFFF;
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    padding: 30px;
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #E2E8F0; }
.modal-header h3 { font-size: 18px; font-weight: 800; color: #0F172A; }
.modal-close { font-size: 24px; cursor: pointer; color: #94A3B8; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1.5px solid #E2E8F0; border-radius: 10px; font-size: 13.5px; outline: none; }
.form-control:focus { border-color: #7C3AED; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

@media (max-width: 1024px) {
    .sidebar { width: 70px; }
    .sidebar-brand span, .sidebar-brand h2, .sidebar-menu span, .nav-category, .sidebar-footer span { display: none; }
    .sidebar-brand { justify-content: center; padding: 15px 0; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar, .content-body { padding-left: 20px; padding-right: 20px; }
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

            <li class="nav-category">Academic & Operations</li>
            <li><a href="circulars.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php" class="active"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="hod_profile.php" style="color:#94A3B8;"><i class="fa-solid fa-id-badge"></i> <span>Profile</span></a>
        <a href="../auth/logout.php" style="color:#F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    
    <div class="topbar">
        <div class="topbar-title">
            <h1>Department Academic Events</h1>
            <p>Department of Computer Science &bull; Event Schedules & Symposiums</p>
        </div>
        <div class="user-profile">
            <div class="role-badge"><i class="fa-solid fa-user-shield"></i> HOD: CS Dept</div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;"><?=htmlspecialchars($hod_name)?></div>
        </div>
    </div>

    <div class="content-body">
        
        <?php if (!empty($msg)): ?><div class="alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>

        <div class="page-header-card">
            <h2><i class="fa-solid fa-calendar-days" style="color: #7C3AED;"></i> Computer Science Department Events</h2>
            <div>
                <a href="dashboard_hod.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openModal()" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Schedule CS Event</button>
            </div>
        </div>

        <div class="events-grid">
            <?php foreach ($events as $ev): ?>
            <div class="event-card">
                <div>
                    <span class="event-date-badge"><i class="fa-regular fa-calendar"></i> <?=htmlspecialchars($ev['event_date'])?></span>
                    <h3 class="event-title"><?=htmlspecialchars($ev['title'])?></h3>
                    <p class="event-desc"><?=htmlspecialchars($ev['description'])?></p>
                </div>
                <div class="event-meta">
                    <div><i class="fa-regular fa-clock"></i> <?=htmlspecialchars($ev['time'])?></div>
                    <div><i class="fa-solid fa-location-dot"></i> <?=htmlspecialchars($ev['venue'])?></div>
                    <div><i class="fa-solid fa-user-tie"></i> <?=htmlspecialchars($ev['coordinator'])?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="evModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Schedule CS Department Event</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="events.php">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Event Name *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. CYBERFEST 2026 / Python Hands-on Workshop" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Event Date *</label>
                    <input type="date" name="event_date" class="form-control" value="<?=date('Y-m-d')?>" required>
                </div>
                <div class="form-group">
                    <label>Time</label>
                    <input type="text" name="time" class="form-control" value="10:00 AM - 04:00 PM">
                </div>
            </div>
            <div class="form-group">
                <label>Venue</label>
                <input type="text" name="venue" class="form-control" value="CS Lab 1 / Auditorium">
            </div>
            <div class="form-group">
                <label>Description & Objectives</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Overview of the event..."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-light" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Schedule Event</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() { document.getElementById('evModal').style.display = 'flex'; }
function closeModal() { document.getElementById('evModal').style.display = 'none'; }
</script>

</body>
</html>
