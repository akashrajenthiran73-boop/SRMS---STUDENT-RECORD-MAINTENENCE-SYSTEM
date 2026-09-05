<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check (Faculty, HOD, Admin Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if (!in_array($role, ['Faculty', 'HOD', 'Admin', 'Super Admin'])) {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied: Faculty Access Only</h2>";
    exit();
}

// 2. Supabase Configuration
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// 3. Action Handler (Approve / Reject Student Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $request_id = $input['request_id'] ?? '';
    $action_status = $input['status'] ?? ''; // 'Approved' or 'Rejected'

    if (empty($request_id) || !in_array($action_status, ['Approved', 'Rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests?id=eq." . urlencode($request_id);
    $payload = json_encode(['status' => $action_status]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 204) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $response]);
    }
    exit();
}

// 4. Fetch Only Student Requests
$student_requests = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests?applicant_role=eq.Student&order=id.desc";
    
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

    $student_requests = json_decode($res, true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Leave Requests - Faculty Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Sidebar Styles */
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
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
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

.menu-category {
    font-size: 10px;
    font-weight: 700;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    padding: 14px 14px 6px 14px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 16px;
    color: #94A3B8;
    text-decoration: none;
    font-weight: 500;
    font-size: 13.5px;
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

.sidebar-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

/* Main Content Area */
.main-content {
    margin-left: 260px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.topbar {
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

.topbar h2 {
    color: #1E3A8A;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: -0.3px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.role-badge {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.content-body {
    padding: 35px 40px;
    flex: 1;
}

.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 30px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.03);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-header h1 {
    color: #0F172A;
    font-size: 22px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
}

.table-wrapper {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    margin-top: 15px;
}

.approval-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.approval-table th, .approval-table td {
    padding: 16px 20px;
    font-size: 13.5px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
}

.approval-table th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.6px;
}

.approval-table tbody tr:hover {
    background: #F8FAFC;
}

/* Badges & Status */
.badge-type {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
}
.type-leave { background: #FEE2E2; color: #991B1B; }
.type-od { background: #E0E7FF; color: #3730A3; }

.status-pending { color: #B45309; font-weight: 700; background: #FEF3C7; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px; }
.status-approved { color: #15803D; font-weight: 700; background: #DCFCE7; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px; }
.status-rejected { color: #B91C1C; font-weight: 700; background: #FEE2E2; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; display: inline-flex; align-items: center; gap: 5px; }

/* Action Buttons */
.btn-act {
    border: none;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: 0.2s ease;
}
.btn-approve { background: #10B981; color: white; margin-right: 6px; }
.btn-approve:hover { background: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25); }
.btn-reject { background: #EF4444; color: white; }
.btn-reject:hover { background: #DC2626; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.25); }

@media(max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .topbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
}
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Faculty Portal</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i><span>Result Analysis</span></a></li>

            <div class="menu-category">Faculty Panel</div>
            <li><a href="student_leave_requests.php" class="active"><i class="fa-solid fa-user-check"></i><span>Student Leave Requests</span></a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-file-pen"></i><span>Apply Leave</span></a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-chart-line"></i><span>Marks CIA</span></a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book"></i><span>My Subjects</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open-reader"></i><span>Syllabus & Materials</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i><span>Assignments</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i><span>Timetable</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i><span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="faculty_profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <h2><i class="fa-solid fa-user-check"></i> Student Leave & OD Approvals</h2>
        <div class="topbar-right">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> Role: Faculty
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="card">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-clipboard-check" style="color: #2563EB;"></i> Manage Student Requests</h1>
                    <p style="color: #64748B; font-size: 13.5px; margin-top: 4px;">Total Leave / OD Requests: <b><?php echo count($student_requests); ?></b></p>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table class="approval-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($student_requests)): ?>
                            <tr><td colspan="6" style="text-align: center; color: #94A3B8; padding: 50px 20px;">No student leave/OD requests available.</td></tr>
                        <?php else: ?>
                            <?php foreach ($student_requests as $req): 
                                $req_id = $req['id'];
                                $applicant = $req['applicant_name'] ?? 'Student';
                                $type = $req['request_type'] ?? 'Leave';
                                $status = $req['status'] ?? 'Pending';
                                $dates = date('d M', strtotime($req['from_date'])) . ' - ' . date('d M Y', strtotime($req['to_date']));
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #0F172A;"><?php echo htmlspecialchars($applicant); ?></strong><br>
                                    <small style="color: #64748B;">ID: <?php echo htmlspecialchars($req['applicant_id'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo strtolower($type) === 'od' ? 'type-od' : 'type-leave'; ?>">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $dates; ?></strong></td>
                                <td style="max-width: 250px; color: #475569;"><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td>
                                    <span class="status-<?php echo strtolower($status); ?>">
                                        <?php echo strtoupper($status); ?>
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($status === 'Pending'): ?>
                                        <button class="btn-act btn-approve" onclick="updateStudentLeaveStatus(<?php echo $req_id; ?>, 'Approved')">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                        <button class="btn-act btn-reject" onclick="updateStudentLeaveStatus(<?php echo $req_id; ?>, 'Rejected')">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-size: 12.5px; font-weight:700;"><i class="fa-solid fa-check-double"></i> Processed</span>
                                    <?php endif; ?>
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

<script>
function updateStudentLeaveStatus(requestId, newStatus) {
    if (!confirm('Are you sure you want to ' + newStatus + ' this student request?')) return;

    fetch('student_leave_requests.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            request_id: requestId,
            status: newStatus
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Request successfully ' + newStatus + '!');
            window.location.reload();
        } else {
            alert('Error updating status: ' + (data.message || JSON.stringify(data.error)));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server Connection Error!');
    });
}
</script>

</body>
</html>