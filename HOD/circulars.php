<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOD') {
    header("Location: ../auth/login.php");
    exit();
}

$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? 'HOD Faculty';
$department = 'CS'; // HOD of Computer Science

require_once __DIR__ . '/../includes/college_data.php';

$msg = '';
$error = '';

// Handle HOD posting department circular
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'Academic';
    $priority = $_POST['priority'] ?? 'Normal';
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = 'Title and circular content are required!';
    } else {
        $saved = save_circular([
            'ref_no' => 'AAGAC/CS/CIR/' . date('Y') . '/' . rand(10, 99),
            'title' => $title,
            'category' => $category,
            'target_dept' => 'CS',
            'priority' => $priority,
            'publish_date' => date('Y-m-d'),
            'published_by' => 'Dr. K. Arulmurugan (HOD Computer Science)',
            'summary' => $summary,
            'content' => $content,
            'attachment' => trim($_POST['attachment'] ?? '')
        ]);

        if ($saved) {
            $msg = 'Department circular issued successfully!';
        } else {
            $error = 'Failed to issue circular.';
        }
    }
}

$filter_cat = $_GET['category'] ?? 'All';
$circulars = get_college_circulars('CS', $filter_cat);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Circulars - HOD Portal</title>
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

/* Main Content */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

.content-body { padding: 30px 36px; }

/* Header Card */
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
    color: #0F172A;
    font-size: 18px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

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
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

.table-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.table-responsive { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: 15px 20px; font-size: 13px; }
th { background: #F8FAFC; color: #475569; font-weight: 700; border-bottom: 1px solid #E2E8F0; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; white-space: nowrap; }
td { border-bottom: 1px solid #F1F5F9; color: #334155; }
tr:hover td { background: #F8FAFC; }

.priority-pill { padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
.p-High { background: #FEE2E2; color: #B91C1C; }
.p-Urgent { background: #FEF3C7; color: #B45309; }
.p-Normal { background: #EFF6FF; color: #1D4ED8; }

.cat-badge { padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; background: #F1F5F9; color: #334155; }
.dept-pill { background: #EDE9FE; color: #6D28D9; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; }

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
    max-width: 620px;
    max-height: 90vh;
    overflow-y: auto;
    padding: 30px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
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
            <li><a href="circulars.php" class="active"><i class="fa-solid fa-envelope-open-text"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
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
            <h1>Department Circulars & Notices</h1>
            <p>Department of Computer Science &bull; College Circulars Hub</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> HOD: CS Dept
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod_name)?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <?php if (!empty($msg)): ?><div class="alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert-error"><?=htmlspecialchars($error)?></div><?php endif; ?>

        <!-- PAGE HEADER ACTIONS -->
        <div class="page-header-card">
            <h2><i class="fa-solid fa-bullhorn" style="color: #7C3AED;"></i> College & CS Department Circulars</h2>
            <div>
                <a href="dashboard_hod.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
                <button onclick="openPublishModal()" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Issue CS Notice</button>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ref Number</th>
                            <th>Date</th>
                            <th>Subject / Title</th>
                            <th>Category</th>
                            <th>Scope</th>
                            <th>Priority</th>
                            <th>Issued By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($circulars)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748B;">No circulars found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($circulars as $c): ?>
                            <tr>
                                <td><span style="font-weight: 700; color: #7C3AED;"><?=htmlspecialchars($c['ref_no'])?></span></td>
                                <td><?=htmlspecialchars($c['publish_date'])?></td>
                                <td>
                                    <strong style="color: #0F172A;"><?=htmlspecialchars($c['title'])?></strong>
                                    <?php if (!empty($c['summary'])): ?>
                                        <p style="font-size: 12px; color: #64748B; margin-top: 3px;"><?=htmlspecialchars($c['summary'])?></p>
                                    <?php endif; ?>
                                </td>
                                <td><span class="cat-badge"><?=htmlspecialchars($c['category'])?></span></td>
                                <td><span class="dept-pill"><?=$c['target_dept'] === 'All' ? 'College-wide' : htmlspecialchars($c['target_dept'])?></span></td>
                                <td><span class="priority-pill p-<?=htmlspecialchars($c['priority'])?>"><?=htmlspecialchars($c['priority'])?></span></td>
                                <td><span style="font-size: 12px;"><?=htmlspecialchars($c['published_by'])?></span></td>
                                <td>
                                    <button class="btn btn-light btn-sm" onclick='viewNoticeModal(<?=json_encode($c)?>)'><i class="fa-solid fa-eye"></i> View</button>
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

<!-- PUBLISH MODAL -->
<div class="modal-overlay" id="pubModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Issue Computer Science Department Notice</h3>
            <span class="modal-close" onclick="closePublishModal()">&times;</span>
        </div>
        <form method="POST" action="circulars.php">
            <input type="hidden" name="action" value="post_notice">
            <div class="form-group">
                <label>Notice / Circular Title *</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. CS Lab Timetable / Project Review Schedule" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="Academic">Academic</option>
                        <option value="Events">Symposium / Fest</option>
                        <option value="Examination">Internal Test / Lab Exam</option>
                        <option value="General">General Notice</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="Normal">Normal</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Brief Summary</label>
                <input type="text" name="summary" class="form-control" placeholder="Short description...">
            </div>
            <div class="form-group">
                <label>Detailed Order / Message *</label>
                <textarea name="content" class="form-control" rows="4" placeholder="Detailed instructions for CS students & faculty..." required></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-light" onclick="closePublishModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Publish</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="vModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="vTitle">Notice Details</h3>
            <span class="modal-close" onclick="closeNoticeModal()">&times;</span>
        </div>
        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:14px; margin-bottom:16px; font-size:12.5px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                <span><strong>Ref:</strong> <span id="vRef" style="color:#7C3AED; font-weight:700;"></span></span>
                <span><strong>Date:</strong> <span id="vDate"></span></span>
            </div>
            <div><strong>Category:</strong> <span id="vCat"></span></div>
        </div>
        <div id="vContent" style="font-size:13.5px; line-height:1.7; color:#334155; white-space:pre-wrap; margin-bottom:20px;"></div>
        <div style="text-align:right;">
            <button class="btn btn-light" onclick="closeNoticeModal()">Close</button>
        </div>
    </div>
</div>

<script>
function openPublishModal() { document.getElementById('pubModal').style.display = 'flex'; }
function closePublishModal() { document.getElementById('pubModal').style.display = 'none'; }
function viewNoticeModal(c) {
    document.getElementById('vTitle').innerText = c.title;
    document.getElementById('vRef').innerText = c.ref_no;
    document.getElementById('vDate').innerText = c.publish_date;
    document.getElementById('vCat').innerText = c.category;
    document.getElementById('vContent').innerText = c.content;
    document.getElementById('vModal').style.display = 'flex';
}
function closeNoticeModal() { document.getElementById('vModal').style.display = 'none'; }
</script>

</body>
</html>
