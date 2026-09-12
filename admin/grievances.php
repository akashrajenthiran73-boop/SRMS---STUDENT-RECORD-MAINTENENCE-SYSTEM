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

// Handle Status Update & Admin Response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? 'Pending';
    $reply = trim($_POST['admin_reply'] ?? '');

    if (!empty($id)) {
        if (update_grievance_status($id, $status, $reply)) {
            $msg = 'Grievance ticket status updated successfully!';
        } else {
            $error = 'Failed to update ticket status.';
        }
    }
}

$filter_dept = $_GET['dept'] ?? 'All';
$filter_status = $_GET['status'] ?? 'All';

$all_grievances = get_student_grievances($filter_dept);
if ($filter_status !== 'All') {
    $all_grievances = array_filter($all_grievances, function($g) use ($filter_status) {
        return ($g['status'] === $filter_status);
    });
}

$departments = get_all_departments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Grievances - SRMS Admin</title>
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
.btn-primary:hover { background: #1D4ED8; }
.btn-light { background: #FFFFFF; color: #475569; border: 1.5px solid #E2E8F0; }
.btn-light:hover { background: #F8FAFC; color: #0F172A; }
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

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-block;
}
.st-Pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.st-In_Review { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.st-Resolved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

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
}
.form-control:focus { border-color: #2563EB; }

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
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php" class="active"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

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
        <h1>Student Grievance & Helpdesk Cell</h1>
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
            <h2><i class="fa-solid fa-headset" style="color: #2563EB;"></i> Student Grievance Redressal Tickets</h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" action="grievances.php" class="filter-bar">
            <div class="filter-item">
                <i class="fa-solid fa-filter"></i>
                <label>Department:</label>
                <select name="dept" class="filter-select" onchange="this.form.submit()">
                    <option value="All" <?php echo $filter_dept === 'All' ? 'selected' : ''; ?>>All Departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['code']; ?>" <?php echo $filter_dept === $d['code'] ? 'selected' : ''; ?>>
                            <?php echo $d['code'] . ' - ' . $d['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Status:</label>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="All" <?php echo $filter_status === 'All' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Review" <?php echo $filter_status === 'In Review' ? 'selected' : ''; ?>>In Review</option>
                    <option value="Resolved" <?php echo $filter_status === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                </select>
            </div>
        </form>

        <!-- GRIEVANCES TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th>Dept</th>
                            <th>Category</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_grievances)): ?>
                            <tr><td colspan="9" style="text-align: center; padding: 40px; color: #64748B;">No grievance tickets found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_grievances as $g): 
                                $statusClass = str_replace(' ', '_', $g['status']);
                            ?>
                            <tr>
                                <td><span style="font-weight: 700; color: #1E40AF; font-size: 12px;"><?php echo htmlspecialchars($g['ticket_no']); ?></span></td>
                                <td><span style="color: #64748B; font-weight: 600;"><?php echo htmlspecialchars($g['submitted_date']); ?></span></td>
                                <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($g['student_name']); ?></strong></td>
                                <td><span style="font-weight: 600;"><?php echo htmlspecialchars($g['reg_no']); ?></span></td>
                                <td><span style="background: #EFF6FF; color: #1D4ED8; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px;"><?php echo htmlspecialchars($g['department']); ?></span></td>
                                <td><span style="font-size: 12px; color: #475569;"><?php echo htmlspecialchars($g['category']); ?></span></td>
                                <td>
                                    <strong style="font-size: 13px; color: #0F172A;"><?php echo htmlspecialchars($g['subject']); ?></strong>
                                    <p style="font-size: 12px; color: #64748B; margin-top: 3px; max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($g['description']); ?></p>
                                </td>
                                <td><span class="status-badge st-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($g['status']); ?></span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick='openResolveModal(<?php echo json_encode($g); ?>)'><i class="fa-solid fa-reply"></i> Review</button>
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

<!-- REVIEW / RESOLVE MODAL -->
<div class="modal-overlay" id="resolveModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Review Student Grievance Ticket</h3>
            <span class="modal-close" onclick="closeResolveModal()">&times;</span>
        </div>
        <form method="POST" action="grievances.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="modalGrievanceId">
            <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 18px; margin-bottom: 20px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span><strong>Ticket:</strong> <span id="mTicket" style="color: #2563EB; font-weight: 700;"></span></span>
                    <span><strong>Student:</strong> <span id="mStudent"></span> (<span id="mReg"></span>)</span>
                </div>
                <div style="margin-bottom: 8px;">
                    <strong>Subject:</strong> <span id="mSubject" style="font-weight: 600; color: #0F172A;"></span>
                </div>
                <div>
                    <strong>Description:</strong>
                    <p id="mDesc" style="color: #475569; margin-top: 4px; line-height: 1.5;"></p>
                </div>
            </div>

            <div class="form-group">
                <label>Ticket Status *</label>
                <select name="status" id="mStatus" class="form-control" required>
                    <option value="Pending">Pending</option>
                    <option value="In Review">In Review (Investigating)</option>
                    <option value="Resolved">Resolved</option>
                </select>
            </div>

            <div class="form-group">
                <label>Official Administrative Response / Remarks *</label>
                <textarea name="admin_reply" id="mReply" class="form-control" rows="4" placeholder="Write response to student explaining action taken or instructions..." required></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn btn-light" onclick="closeResolveModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check-double"></i> Update Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResolveModal(g) {
    document.getElementById('modalGrievanceId').value = g.id;
    document.getElementById('mTicket').innerText = g.ticket_no;
    document.getElementById('mStudent').innerText = g.student_name;
    document.getElementById('mReg').innerText = g.reg_no;
    document.getElementById('mSubject').innerText = g.subject;
    document.getElementById('mDesc').innerText = g.description;
    document.getElementById('mStatus').value = g.status;
    document.getElementById('mReply').value = g.admin_reply || '';
    document.getElementById('resolveModal').style.display = 'flex';
}

function closeResolveModal() {
    document.getElementById('resolveModal').style.display = 'none';
}
</script>

</body>
</html>
