<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

// Handle Form Submission: Add / Edit Department
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $degrees = trim($_POST['degrees'] ?? '');
    $hod_name = trim($_POST['hod_name'] ?? '');
    $hod_email = trim($_POST['hod_email'] ?? '');
    $faculty_count = (int)($_POST['faculty_count'] ?? 0);
    $student_count = (int)($_POST['student_count'] ?? 0);
    $status = $_POST['status'] ?? 'Active';
    $established = (int)($_POST['established'] ?? date('Y'));
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name)) {
        $error = 'Department Code and Name are required!';
    } else {
        $success = save_department([
            'code' => $code,
            'name' => $name,
            'degrees' => $degrees,
            'hod_name' => $hod_name,
            'hod_email' => $hod_email,
            'faculty_count' => $faculty_count,
            'student_count' => $student_count,
            'status' => $status,
            'established' => $established,
            'description' => $description
        ]);

        if ($success) {
            $msg = ($action === 'edit') ? 'Department details updated successfully!' : 'New department registered successfully!';
        } else {
            $error = 'Failed to save department details.';
        }
    }
}

$departments = get_all_departments();
$metrics = get_college_metrics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>College Departments - SRMS Admin</title>
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
    position: relative;
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
.navbar h1 {
    color: #1E3A8A;
    font-size: 22px;
    font-weight: 800;
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

.content-body {
    padding: 35px 40px;
    flex: 1;
}

/* Metric mini cards */
.metrics-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.metric-box {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
}
.metric-box h4 {
    font-size: 12px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}
.metric-box .val {
    font-size: 26px;
    font-weight: 800;
    color: #0F172A;
}
.metric-box .icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.ib-blue { background: #EFF6FF; color: #2563EB; }
.ib-purple { background: #F3E8FF; color: #7C3AED; }
.ib-emerald { background: #ECFDF5; color: #059669; }
.ib-amber { background: #FEF3C7; color: #D97706; }

/* Page Header Card */
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

.badge-code {
    background: #EFF6FF;
    color: #1D4ED8;
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 800;
    font-size: 12px;
    display: inline-block;
}
.badge-core {
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #FDE68A;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 10.5px;
    font-weight: 700;
    margin-left: 6px;
}
.badge-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
}
.badge-active { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }

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
            <li><a href="manage_departments.php" class="active"><i class="fa-solid fa-building-columns"></i> <span>Manage Departments</span></a></li>
            <li><a href="circulars.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
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
        <h1>College Departments Directory</h1>
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

        <!-- METRICS STRIP -->
        <div class="metrics-strip">
            <div class="metric-box">
                <div>
                    <h4>Total Departments</h4>
                    <div class="val"><?php echo $metrics['total_departments']; ?></div>
                </div>
                <div class="icon ib-blue"><i class="fa-solid fa-building-columns"></i></div>
            </div>
            <div class="metric-box">
                <div>
                    <h4>Active Core Dept</h4>
                    <div class="val" style="font-size: 18px; color: #2563EB;">B.Sc CS</div>
                </div>
                <div class="icon ib-purple"><i class="fa-solid fa-microchip"></i></div>
            </div>
            <div class="metric-box">
                <div>
                    <h4>College Enrolled</h4>
                    <div class="val"><?php echo number_format($metrics['total_students']); ?></div>
                </div>
                <div class="icon ib-emerald"><i class="fa-solid fa-user-graduate"></i></div>
            </div>
            <div class="metric-box">
                <div>
                    <h4>Total Faculty</h4>
                    <div class="val"><?php echo $metrics['total_faculty']; ?></div>
                </div>
                <div class="icon ib-amber"><i class="fa-solid fa-chalkboard-user"></i></div>
            </div>
        </div>

        <!-- PAGE HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-sitemap" style="color: #2563EB;"></i> Arignar Anna Government Arts College - Departments</h2>
            <div>
                <a href="dashboard_admin.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openAddModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add New Department</button>
            </div>
        </div>

        <!-- DEPARTMENTS TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Degree / Programs</th>
                            <th>Head of Department</th>
                            <th>Faculty</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($departments as $d): 
                        ?>
                        <tr>
                            <td><strong><?php echo $i++; ?></strong></td>
                            <td><span class="badge-code"><?php echo htmlspecialchars($d['code']); ?></span></td>
                            <td>
                                <strong style="color: #0F172A; font-size: 14px;"><?php echo htmlspecialchars($d['name']); ?></strong>
                                <?php if (!empty($d['is_core'])): ?>
                                    <span class="badge-core"><i class="fa-solid fa-star"></i> Core Active System</span>
                                <?php endif; ?>
                                <?php if (!empty($d['category'])): ?>
                                    <span style="display: inline-block; font-size: 11px; padding: 2px 8px; border-radius: 4px; background: <?php echo $d['category'] === 'Arts & Commerce' ? '#FEF3C7' : '#EFF6FF'; ?>; color: <?php echo $d['category'] === 'Arts & Commerce' ? '#92400E' : '#1E40AF'; ?>; font-weight: 700; margin-left: 6px;"><?php echo htmlspecialchars($d['category']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span style="color: #475569; font-weight: 500;"><?php echo htmlspecialchars($d['degrees']); ?></span></td>
                            <td><span style="font-weight: 600; color: #1E40AF;"><?php echo htmlspecialchars($d['hod_name']); ?></span></td>
                            <td><span style="font-weight: 700;"><?php echo (int)($d['faculty_count']); ?></span></td>
                            <td><span style="font-weight: 700; color: #059669;"><?php echo (int)($d['student_count']); ?></span></td>
                            <td><span class="badge-status badge-active"><?php echo htmlspecialchars($d['status'] ?? 'Active'); ?></span></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-light btn-sm" onclick='openEditModal(<?php echo json_encode($d); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <a href="student_records.php?dept=<?php echo urlencode($d['code']); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-users"></i> Records</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- ADD/EDIT MODAL -->
<div class="modal-overlay" id="deptModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="modalTitle">Add New Department</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="manage_departments.php">
            <input type="hidden" name="action" id="formAction" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>Department Code *</label>
                    <input type="text" name="code" id="deptCode" class="form-control" placeholder="e.g. CS, BCA, MATH" required>
                </div>
                <div class="form-group">
                    <label>Established Year</label>
                    <input type="number" name="established" id="deptYear" class="form-control" value="2000">
                </div>
            </div>
            <div class="form-group">
                <label>Department Name *</label>
                <input type="text" name="name" id="deptName" class="form-control" placeholder="e.g. Department of Computer Applications" required>
            </div>
            <div class="form-group">
                <label>Offered Degrees / Programs</label>
                <input type="text" name="degrees" id="deptDegrees" class="form-control" placeholder="e.g. B.Sc Computer Science, M.Sc Computer Science">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Head of Department (HOD)</label>
                    <input type="text" name="hod_name" id="deptHOD" class="form-control" placeholder="e.g. Dr. K. Arulmurugan, Ph.D.">
                </div>
                <div class="form-group">
                    <label>HOD Official Email</label>
                    <input type="email" name="hod_email" id="deptEmail" class="form-control" placeholder="e.g. hod.cs@aagacvpm.edu.in">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total Faculty Count</label>
                    <input type="number" name="faculty_count" id="deptFaculty" class="form-control" value="6">
                </div>
                <div class="form-group">
                    <label>Enrolled Students Count</label>
                    <input type="number" name="student_count" id="deptStudents" class="form-control" value="150">
                </div>
            </div>
            <div class="form-group">
                <label>Description / Overview</label>
                <textarea name="description" id="deptDesc" class="form-control" rows="3" placeholder="Overview of academic curriculum and labs..."></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="deptStatus" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px;">
                <button type="button" class="btn btn-light" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save Department</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add New Department';
    document.getElementById('formAction').value = 'add';
    document.getElementById('deptCode').value = '';
    document.getElementById('deptCode').readOnly = false;
    document.getElementById('deptName').value = '';
    document.getElementById('deptDegrees').value = '';
    document.getElementById('deptHOD').value = '';
    document.getElementById('deptEmail').value = '';
    document.getElementById('deptFaculty').value = '6';
    document.getElementById('deptStudents').value = '150';
    document.getElementById('deptDesc').value = '';
    document.getElementById('deptStatus').value = 'Active';
    document.getElementById('deptModal').style.display = 'flex';
}

function openEditModal(d) {
    document.getElementById('modalTitle').innerText = 'Edit Department (' + d.code + ')';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('deptCode').value = d.code;
    document.getElementById('deptCode').readOnly = true;
    document.getElementById('deptName').value = d.name || '';
    document.getElementById('deptDegrees').value = d.degrees || '';
    document.getElementById('deptHOD').value = d.hod_name || '';
    document.getElementById('deptEmail').value = d.hod_email || '';
    document.getElementById('deptFaculty').value = d.faculty_count || 0;
    document.getElementById('deptStudents').value = d.student_count || 0;
    document.getElementById('deptDesc').value = d.description || '';
    document.getElementById('deptStatus').value = d.status || 'Active';
    document.getElementById('deptModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('deptModal').style.display = 'none';
}
window.onclick = function(e) {
    if (e.target == document.getElementById('deptModal')) {
        closeModal();
    }
}
</script>

</body>
</html>
