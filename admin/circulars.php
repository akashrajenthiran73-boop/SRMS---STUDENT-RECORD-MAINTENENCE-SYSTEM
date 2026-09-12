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

// Handle Delete Circular
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (delete_circular($_GET['id'])) {
        $msg = 'Circular deleted successfully!';
    } else {
        $error = 'Failed to delete circular.';
    }
}

// Handle Add / Edit Circular
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'General';
    $target_dept = $_POST['target_dept'] ?? 'All';
    $priority = $_POST['priority'] ?? 'Normal';
    $publish_date = $_POST['publish_date'] ?? date('Y-m-d');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $ref_no = trim($_POST['ref_no'] ?? '');

    if (empty($title) || empty($content)) {
        $error = 'Circular title and content are required!';
    } else {
        $saved = save_circular([
            'id' => $_POST['id'] ?? null,
            'ref_no' => !empty($ref_no) ? $ref_no : ('AAGAC/CIR/' . date('Y') . '/' . rand(100, 999)),
            'title' => $title,
            'category' => $category,
            'target_dept' => $target_dept,
            'priority' => $priority,
            'publish_date' => $publish_date,
            'published_by' => 'Office of the Principal / Admin',
            'summary' => $summary,
            'content' => $content,
            'attachment' => trim($_POST['attachment'] ?? '')
        ]);

        if ($saved) {
            $msg = 'Circular published successfully!';
        } else {
            $error = 'Failed to publish circular.';
        }
    }
}

$filter_dept = $_GET['dept'] ?? 'All';
$filter_cat = $_GET['category'] ?? 'All';
$circulars = get_college_circulars($filter_dept, $filter_cat);
$departments = get_all_departments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>College Circulars - SRMS Admin</title>
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
    flex-wrap: wrap;
}
.filter-item { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #475569; }
.filter-select {
    padding: 8px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 8px;
    font-size: 13px;
    background: #F8FAFC;
    outline: none;
    color: #0F172A;
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
.btn-danger:hover { background: #DC2626; }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

/* Table Card */
.table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: 16px 20px; font-size: 13px; }
th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    border-bottom: 1.5px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.6px;
    white-space: nowrap;
}
td { border-bottom: 1px solid #F1F5F9; color: #334155; }
tr:hover td { background: #F8FAFC; }

.priority-pill {
    padding: 3px 9px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    display: inline-block;
}
.p-High { background: #FEE2E2; color: #B91C1C; }
.p-Urgent { background: #FEF3C7; color: #B45309; }
.p-Normal { background: #EFF6FF; color: #1D4ED8; }

.cat-badge {
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: #F1F5F9;
    color: #334155;
    border: 1px solid #E2E8F0;
}
.dept-pill {
    background: #EDE9FE;
    color: #6D28D9;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 11px;
}

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
    max-width: 650px;
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
    transition: border-color 0.2s;
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
            <li><a href="circulars.php" class="active"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
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
        <h1>College Circulars & Official Notices</h1>
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
            <h2><i class="fa-solid fa-newspaper" style="color: #2563EB;"></i> Official College Circulars Feed</h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openPublishModal()" class="btn btn-primary"><i class="fa-solid fa-bullhorn"></i> Issue New Circular</button>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" action="circulars.php" class="filter-bar">
            <div class="filter-item">
                <i class="fa-solid fa-filter"></i>
                <label>Department:</label>
                <select name="dept" class="filter-select" onchange="this.form.submit()">
                    <option value="All" <?php echo $filter_dept === 'All' ? 'selected' : ''; ?>>All Departments (College-wide)</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['code']; ?>" <?php echo $filter_dept === $d['code'] ? 'selected' : ''; ?>>
                            <?php echo $d['code'] . ' - ' . $d['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Category:</label>
                <select name="category" class="filter-select" onchange="this.form.submit()">
                    <option value="All" <?php echo $filter_cat === 'All' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="Examination" <?php echo $filter_cat === 'Examination' ? 'selected' : ''; ?>>Examination</option>
                    <option value="Academic" <?php echo $filter_cat === 'Academic' ? 'selected' : ''; ?>>Academic</option>
                    <option value="Scholarship" <?php echo $filter_cat === 'Scholarship' ? 'selected' : ''; ?>>Scholarship / Fees</option>
                    <option value="Events" <?php echo $filter_cat === 'Events' ? 'selected' : ''; ?>>Events / Symposiums</option>
                    <option value="Holiday" <?php echo $filter_cat === 'Holiday' ? 'selected' : ''; ?>>Holidays</option>
                    <option value="General" <?php echo $filter_cat === 'General' ? 'selected' : ''; ?>>General Notice</option>
                </select>
            </div>
            <?php if ($filter_dept !== 'All' || $filter_cat !== 'All'): ?>
                <a href="circulars.php" class="btn btn-light btn-sm" style="margin-left: auto;"><i class="fa-solid fa-xmark"></i> Clear Filters</a>
            <?php endif; ?>
        </form>

        <!-- CIRCULARS TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ref Number</th>
                            <th>Date</th>
                            <th>Subject / Title</th>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Priority</th>
                            <th>Published By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($circulars)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748B;">No circulars found matching the filter criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($circulars as $c): ?>
                            <tr>
                                <td><span style="font-weight: 700; color: #1E40AF; font-size: 12px;"><?php echo htmlspecialchars($c['ref_no']); ?></span></td>
                                <td><span style="color: #64748B; font-weight: 600;"><?php echo htmlspecialchars($c['publish_date']); ?></span></td>
                                <td>
                                    <strong style="color: #0F172A; font-size: 13.5px;"><?php echo htmlspecialchars($c['title']); ?></strong>
                                    <?php if (!empty($c['summary'])): ?>
                                        <p style="font-size: 12px; color: #64748B; margin-top: 3px;"><?php echo htmlspecialchars($c['summary']); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="cat-badge"><?php echo htmlspecialchars($c['category']); ?></span></td>
                                <td>
                                    <?php if ($c['target_dept'] === 'All'): ?>
                                        <span class="dept-pill" style="background: #E0E7FF; color: #3730A3;">All College</span>
                                    <?php else: ?>
                                        <span class="dept-pill"><?php echo htmlspecialchars($c['target_dept']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="priority-pill p-<?php echo htmlspecialchars($c['priority']); ?>"><?php echo htmlspecialchars($c['priority']); ?></span></td>
                                <td><span style="font-size: 12px; color: #475569; font-weight: 500;"><?php echo htmlspecialchars($c['published_by']); ?></span></td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button class="btn btn-light btn-sm" onclick='viewCircularModal(<?php echo json_encode($c); ?>)'><i class="fa-solid fa-eye"></i> View</button>
                                        <a href="circulars.php?action=delete&id=<?php echo urlencode($c['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this circular?');"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- PUBLISH CIRCULAR MODAL -->
<div class="modal-overlay" id="publishModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Issue Official College Circular</h3>
            <span class="modal-close" onclick="closePublishModal()">&times;</span>
        </div>
        <form method="POST" action="circulars.php">
            <input type="hidden" name="action" value="publish">
            <div class="form-row">
                <div class="form-group">
                    <label>Circular Reference No</label>
                    <input type="text" name="ref_no" class="form-control" value="AAGAC/CIR/<?php echo date('Y'); ?>/<?php echo rand(100, 999); ?>">
                </div>
                <div class="form-group">
                    <label>Date of Issue</label>
                    <input type="date" name="publish_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Circular Subject / Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. CIA-2 Test Schedule / University Exam Hall Ticket" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="Academic">Academic</option>
                        <option value="Examination">Examination</option>
                        <option value="Scholarship">Scholarship / Fee</option>
                        <option value="Events">Events / Symposium</option>
                        <option value="Holiday">Holiday Declaration</option>
                        <option value="General">General Notice</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Target Department Scope</label>
                    <select name="target_dept" class="form-control">
                        <option value="All">All Departments (College-wide)</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['code']; ?>"><?php echo $d['code'] . ' - ' . $d['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Priority Level</label>
                    <select name="priority" class="form-control">
                        <option value="Normal">Normal</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Attachment Document / File Name</label>
                    <input type="text" name="attachment" class="form-control" placeholder="e.g. exam_schedule.pdf (optional)">
                </div>
            </div>
            <div class="form-group">
                <label>Brief Summary (1-2 lines)</label>
                <input type="text" name="summary" class="form-control" placeholder="Brief outline for preview banner...">
            </div>
            <div class="form-group">
                <label>Full Circular Order / Content *</label>
                <textarea name="content" class="form-control" rows="5" placeholder="Official announcement text, instructions, and deadlines..." required></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn btn-light" onclick="closePublishModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish Circular</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW CIRCULAR MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal-box" style="max-width: 680px;">
        <div class="modal-header">
            <h3 id="viewTitle" style="font-size: 16px;">Circular Details</h3>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px; margin-bottom: 18px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12.5px;">
                <span><strong>Ref:</strong> <span id="viewRef" style="color: #2563EB;"></span></span>
                <span><strong>Date:</strong> <span id="viewDate"></span></span>
            </div>
            <div style="display: flex; gap: 10px; font-size: 12px;">
                <span><strong>Category:</strong> <span id="viewCat" class="cat-badge"></span></span>
                <span><strong>Department:</strong> <span id="viewDept" class="dept-pill"></span></span>
            </div>
        </div>
        <div style="line-height: 1.7; font-size: 14px; color: #334155; white-space: pre-wrap; margin-bottom: 24px;" id="viewBody"></div>
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E2E8F0; padding-top: 16px;">
            <span style="font-size: 12px; color: #64748B;"><i class="fa-solid fa-stamp" style="color:#2563EB;"></i> Issued by Principal Office</span>
            <button class="btn btn-light btn-sm" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openPublishModal() {
    document.getElementById('publishModal').style.display = 'flex';
}
function closePublishModal() {
    document.getElementById('publishModal').style.display = 'none';
}

function viewCircularModal(c) {
    document.getElementById('viewTitle').innerText = c.title;
    document.getElementById('viewRef').innerText = c.ref_no;
    document.getElementById('viewDate').innerText = c.publish_date;
    document.getElementById('viewCat').innerText = c.category;
    document.getElementById('viewDept').innerText = (c.target_dept === 'All' ? 'All College' : c.target_dept);
    document.getElementById('viewBody').innerText = c.content;
    document.getElementById('viewModal').style.display = 'flex';
}
function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}
</script>

</body>
</html>
