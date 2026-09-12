<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
require_once __DIR__ . '/../includes/college_data.php';

$msg = '';
$error = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_event($_GET['id'])) {
        $msg = 'Event removed from calendar successfully!';
    } else {
        $error = 'Failed to delete event.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Academic';
    $department = $_POST['department'] ?? 'All';
    $event_date = $_POST['event_date'] ?? date('Y-m-d');
    $time = trim($_POST['time'] ?? '10:00 AM');
    $venue = trim($_POST['venue'] ?? 'College Auditorium');
    $coordinator = trim($_POST['coordinator'] ?? 'Faculty Coordinator');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($event_date)) {
        $error = 'Event Title and Date are required!';
    } else {
        $saved = save_event([
            'id' => $_POST['id'] ?? null,
            'title' => $title,
            'category' => $category,
            'department' => $department,
            'event_date' => $event_date,
            'time' => $time,
            'venue' => $venue,
            'coordinator' => $coordinator,
            'description' => $description
        ]);

        if ($saved) {
            $msg = 'College event scheduled successfully!';
        } else {
            $error = 'Failed to save event.';
        }
    }
}

$filter_dept = $_GET['dept'] ?? 'All';
$events = get_college_events($filter_dept);
$departments = get_all_departments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Academic Events & Calendar - SRMS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #0F172A;
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
    background: #0B132B;
    color: white;
    padding: 22px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.06);
    z-index: 100;
}
.sidebar-brand {
    text-align: center;
    padding-bottom: 18px;
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
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    display: block;
    margin-top: 4px;
}
.sidebar-nav-container {
    flex-grow: 1;
    overflow-y: auto;
    margin-top: 14px;
    padding-right: 4px;
}
.sidebar-nav-container::-webkit-scrollbar { width: 4px; }
.sidebar-nav-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }

.sidebar-menu { list-style: none; }
.sidebar-menu li { margin-bottom: 4px; }
.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 10px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.25s ease;
}
.sidebar-menu a:hover {
    color: #F8FAFC;
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(3px);
}
.sidebar-menu a.active {
    background: rgba(245, 158, 11, 0.14);
    color: #F59E0B;
    font-weight: 700;
}
.sidebar-menu a i { font-size: 15px; width: 20px; text-align: center; }

.menu-divider {
    margin: 14px 8px;
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

/* Main Content */
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
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar h1 { color: #1E3A8A; font-size: 22px; font-weight: 800; }
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

.content-body { padding: 35px 40px; flex: 1; }

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
.page-header-card h2 {
    color: #1E3A8A;
    font-weight: 800;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Action Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.25s ease;
    cursor: pointer;
    border: none;
}
.btn-primary { background: #2563EB; color: #FFFFFF; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-primary:hover { background: #1D4ED8; transform: translateY(-2px); }
.btn-light { background: #FFFFFF; color: #475569; border: 1.5px solid #E2E8F0; }
.btn-light:hover { background: #F8FAFC; color: #0F172A; }
.btn-danger { background: #EF4444; color: #FFFFFF; }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

/* Filter Bar */
.filter-bar {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 14px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    align-items: center;
}
.filter-select {
    padding: 8px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    font-size: 13px;
    background: #F8FAFC;
    outline: none;
}

/* Events Grid */
.events-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 22px;
}
.event-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s ease;
}
.event-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
    border-color: #CBD5E1;
}
.event-date-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 6px 14px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.event-dept-badge {
    background: #F3E8FF;
    color: #7C3AED;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.event-cat-badge {
    background: #ECFDF5;
    color: #059669;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.event-title {
    font-size: 16px;
    font-weight: 800;
    color: #0F172A;
    margin: 14px 0 8px 0;
    line-height: 1.4;
}
.event-desc {
    font-size: 13px;
    color: #64748B;
    line-height: 1.6;
    margin-bottom: 16px;
    flex-grow: 1;
}
.event-meta {
    border-top: 1px solid #F1F5F9;
    padding-top: 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 12.5px;
    color: #475569;
}
.event-meta i { width: 18px; color: #64748B; text-align: center; }

.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

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
    max-height: 90vh;
    overflow-y: auto;
    padding: 32px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #E2E8F0;
}
.modal-header h3 { font-size: 18px; font-weight: 800; color: #1E3A8A; }
.modal-close { font-size: 24px; cursor: pointer; color: #94A3B8; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12.5px; font-weight: 700; color: #475569; margin-bottom: 6px; }
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
}
.form-control:focus { border-color: #2563EB; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 16px 8px; }
    .sidebar-brand h2 span, .sidebar-brand span, .sidebar-menu a span, .menu-heading { display: none; }
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
        <span>Arignar Anna Government Arts College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">College Operations</div>
            <li><a href="manage_departments.php"><i class="fa-solid fa-building-columns"></i> <span>Manage Departments</span></a></li>
            <li><a href="circulars.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php" class="active"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-chart-line"></i> <span>Reports</span></a></li>
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
        <h1>College Events & Academic Calendar</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- PAGE HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-calendar-days" style="color: #2563EB;"></i> Upcoming College Events & Schedules</h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openAddEventModal()" class="btn btn-primary"><i class="fa-solid fa-calendar-plus"></i> Schedule New Event</button>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" action="events.php" class="filter-bar">
            <i class="fa-solid fa-filter" style="color: #64748B;"></i>
            <label style="font-size: 13px; font-weight: 600; color: #475569;">Filter by Department:</label>
            <select name="dept" class="filter-select" onchange="this.form.submit()">
                <option value="All" <?php echo $filter_dept === 'All' ? 'selected' : ''; ?>>All Departments (College-wide)</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?php echo $d['code']; ?>" <?php echo $filter_dept === $d['code'] ? 'selected' : ''; ?>>
                        <?php echo $d['code'] . ' - ' . $d['name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- EVENTS GRID -->
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 50px; background: #FFFFFF; border-radius: 16px; border: 1px solid #E2E8F0; color: #64748B;">
                    <i class="fa-solid fa-calendar-xmark" style="font-size: 36px; color: #CBD5E1; margin-bottom: 10px; display: block;"></i>
                    No scheduled events found for this department.
                </div>
            <?php else: ?>
                <?php foreach ($events as $ev): ?>
                <div class="event-card">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <span class="event-date-badge"><i class="fa-regular fa-calendar"></i> <?php echo htmlspecialchars($ev['event_date']); ?></span>
                            <div style="display: flex; gap: 6px;">
                                <span class="event-dept-badge"><?php echo $ev['department'] === 'All' ? 'College-wide' : htmlspecialchars($ev['department']); ?></span>
                                <span class="event-cat-badge"><?php echo htmlspecialchars($ev['category']); ?></span>
                            </div>
                        </div>
                        <h3 class="event-title"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <p class="event-desc"><?php echo htmlspecialchars($ev['description']); ?></p>
                    </div>

                    <div>
                        <div class="event-meta">
                            <div><i class="fa-regular fa-clock"></i> <strong>Time:</strong> <?php echo htmlspecialchars($ev['time']); ?></div>
                            <div><i class="fa-solid fa-location-dot"></i> <strong>Venue:</strong> <?php echo htmlspecialchars($ev['venue']); ?></div>
                            <div><i class="fa-solid fa-user-tie"></i> <strong>In-charge:</strong> <?php echo htmlspecialchars($ev['coordinator']); ?></div>
                        </div>
                        <div style="margin-top: 14px; text-align: right;">
                            <a href="events.php?action=delete&id=<?php echo urlencode($ev['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this event?');"><i class="fa-solid fa-trash"></i> Delete</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ADD EVENT MODAL -->
<div class="modal-overlay" id="eventModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Schedule College Event</h3>
            <span class="modal-close" onclick="closeEventModal()">&times;</span>
        </div>
        <form method="POST" action="events.php">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Event Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. CYBERFEST 2026 / National Science Day / Annual Sports Meet" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="Symposium">Symposium / Fest</option>
                        <option value="Academic">Academic Seminar</option>
                        <option value="Examination">University Examination</option>
                        <option value="Sports">Sports & Athletics</option>
                        <option value="Cultural">Cultural / Annual Day</option>
                        <option value="Workshop">Hands-on Workshop</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Organizing Department</label>
                    <select name="department" class="form-control">
                        <option value="All">All Departments (College-wide)</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['code']; ?>"><?php echo $d['code'] . ' - ' . $d['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Event Date *</label>
                    <input type="date" name="event_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Event Timing</label>
                    <input type="text" name="time" class="form-control" placeholder="e.g. 09:30 AM - 04:30 PM" value="10:00 AM - 04:00 PM">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Venue / Location</label>
                    <input type="text" name="venue" class="form-control" placeholder="e.g. Auditorium / CS Lab 1 / Grounds" value="Auditorium">
                </div>
                <div class="form-group">
                    <label>Faculty Coordinator</label>
                    <input type="text" name="coordinator" class="form-control" placeholder="e.g. Dr. K. Arulmurugan (HOD CS)">
                </div>
            </div>
            <div class="form-group">
                <label>Event Description & Agenda</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Brief outline of event, chief guests, and participating guidelines..."></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn btn-light" onclick="closeEventModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add to Calendar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddEventModal() {
    document.getElementById('eventModal').style.display = 'flex';
}
function closeEventModal() {
    document.getElementById('eventModal').style.display = 'none';
}
</script>

</body>
</html>
