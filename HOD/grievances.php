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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? 'In Review';
    $reply = trim($_POST['admin_reply'] ?? '');

    if (!empty($id)) {
        if (update_grievance_status($id, $status, $reply)) {
            $msg = 'Student grievance status updated!';
        } else {
            $error = 'Failed to update grievance.';
        }
    }
}

$grievances = get_student_grievances('CS');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Grievances - HOD Portal</title>
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
.btn-primary { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }
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

.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.st-Pending { background: #FEF3C7; color: #B45309; }
.st-In_Review { background: #EFF6FF; color: #1D4ED8; }
.st-Resolved { background: #ECFDF5; color: #047857; }

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
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Academic Events</span></a></li>
            <li><a href="grievances.php" class="active"><i class="fa-solid fa-comments"></i> <span>Student Grievances</span></a></li>

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
            <h1>CS Student Grievances & Queries</h1>
            <p>Department of Computer Science &bull; Student Helpdesk Redressal</p>
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
            <h2><i class="fa-solid fa-headset" style="color: #7C3AED;"></i> Computer Science Student Tickets</h2>
            <div>
                <a href="dashboard_hod.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Reg No</th>
                            <th>Category</th>
                            <th>Subject</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($grievances)): ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748B;">No grievances reported in Computer Science.</td></tr>
                        <?php else: ?>
                            <?php foreach ($grievances as $g): 
                                $stClass = str_replace(' ', '_', $g['status']);
                            ?>
                            <tr>
                                <td><span style="font-weight: 700; color: #7C3AED;"><?=htmlspecialchars($g['ticket_no'])?></span></td>
                                <td><?=htmlspecialchars($g['submitted_date'])?></td>
                                <td><strong style="color: #0F172A;"><?=htmlspecialchars($g['student_name'])?></strong></td>
                                <td><?=htmlspecialchars($g['reg_no'])?></td>
                                <td><?=htmlspecialchars($g['category'])?></td>
                                <td>
                                    <strong><?=htmlspecialchars($g['subject'])?></strong>
                                    <p style="font-size: 12px; color: #64748B; margin-top: 2px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?=htmlspecialchars($g['description'])?></p>
                                </td>
                                <td><span class="status-badge st-<?=$stClass?>"><?=htmlspecialchars($g['status'])?></span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick='openModal(<?=json_encode($g)?>)'><i class="fa-solid fa-reply"></i> Reply</button>
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

<!-- MODAL -->
<div class="modal-overlay" id="grvModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Update Student Grievance Ticket</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <form method="POST" action="grievances.php">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="mId">
            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:14px; margin-bottom:16px; font-size:12.5px;">
                <div style="margin-bottom:6px;"><strong>Student:</strong> <span id="mStudent"></span> (<span id="mReg"></span>)</div>
                <div style="margin-bottom:6px;"><strong>Subject:</strong> <span id="mSub" style="color:#0F172A; font-weight:700;"></span></div>
                <div><strong>Message:</strong> <p id="mDesc" style="color:#475569; margin-top:3px;"></p></div>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="mStatus" class="form-control">
                    <option value="Pending">Pending</option>
                    <option value="In Review">In Review</option>
                    <option value="Resolved">Resolved</option>
                </select>
            </div>
            <div class="form-group">
                <label>HOD Remarks / Resolution Response *</label>
                <textarea name="admin_reply" id="mReply" class="form-control" rows="3" placeholder="Action taken or guidance for student..." required></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:16px;">
                <button type="button" class="btn btn-light" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(g) {
    document.getElementById('mId').value = g.id;
    document.getElementById('mStudent').innerText = g.student_name;
    document.getElementById('mReg').innerText = g.reg_no;
    document.getElementById('mSub').innerText = g.subject;
    document.getElementById('mDesc').innerText = g.description;
    document.getElementById('mStatus').value = g.status;
    document.getElementById('mReply').value = g.admin_reply || '';
    document.getElementById('grvModal').style.display = 'flex';
}
function closeModal() { document.getElementById('grvModal').style.display = 'none'; }
</script>

</body>
</html>
