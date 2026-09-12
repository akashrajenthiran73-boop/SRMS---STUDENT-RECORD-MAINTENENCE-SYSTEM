<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check (HOD / Admin Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if (!in_array($role, ['HOD', 'Admin', 'Super Admin'])) {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied: HOD Only Page</h2>";
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

// 3. Approve / Reject Action Handler (PATCH)
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

// 4. Fetch All Leave & OD Requests
$requests_list = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests?select=*&order=id.desc";
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

    $requests_list = json_decode($res, true) ?? [];
}
?>
<?php
$hod_name = $_SESSION['hod_name'] ?? $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'HOD Faculty';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave / OD Approvals - HOD Portal</title>
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
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #F3E8FF; color: #7C3AED; border: 1px solid #E9D5FF; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: #FFFFFF;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.page-header h1 {
    color: #0F172A;
    font-size: 18px;
    font-weight: 800;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 14px;
    color: #94A3B8;
    font-size: 13px;
}

.search-input {
    padding: 9px 14px 9px 38px;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #0F172A;
    outline: none;
    width: 220px;
    transition: all 0.2s ease;
}

.search-input:focus {
    border-color: #7C3AED;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
    width: 260px;
}

.btn-back {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #E2E8F0;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
}

.btn-back:hover {
    background: #E2E8F0;
    color: #0F172A;
}

/* Table Container Card */
.table-card {
    background: #FFFFFF;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.table-responsive {
    overflow-x: auto;
}

.approval-table { 
    width: 100%; 
    border-collapse: collapse; 
    text-align: left; 
}

.approval-table th, .approval-table td { 
    padding: 15px 20px; 
    font-size: 13px; 
}

.approval-table th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700; 
    text-transform: uppercase; 
    font-size: 11px; 
    letter-spacing: 0.6px;
    border-bottom: 1px solid #E2E8F0;
    white-space: nowrap;
}

.approval-table td {
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
    vertical-align: middle;
}

.approval-table tr:last-child td {
    border-bottom: none;
}

.approval-table tbody tr:hover td {
    background: #F8FAFC;
}

/* Badges */
.badge-role { 
    font-size: 11px; 
    font-weight: 700; 
    padding: 4px 10px; 
    border-radius: 20px; 
    text-transform: uppercase; 
    display: inline-block;
}
.role-student { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.role-faculty { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }

.badge-type { 
    font-size: 11px; 
    font-weight: 700; 
    padding: 4px 10px; 
    border-radius: 20px; 
    display: inline-block;
}
.type-leave { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
.type-od { background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE; }

.status-pill {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.status-pending { color: #B45309; background: #FEF3C7; border: 1px solid #FDE68A; }
.status-approved { color: #047857; background: #ECFDF5; border: 1px solid #A7F3D0; }
.status-rejected { color: #B91C1C; background: #FEF2F2; border: 1px solid #FECACA; }

/* Action Buttons */
.btn-act { 
    border: none; 
    padding: 6px 12px; 
    border-radius: 8px; 
    font-size: 12px; 
    font-weight: 600; 
    cursor: pointer; 
    display: inline-flex; 
    align-items: center; 
    gap: 5px; 
    transition: 0.2s; 
}
.btn-approve { background: #10B981; color: white; margin-right: 5px; }
.btn-approve:hover { background: #059669; }
.btn-reject { background: #EF4444; color: white; }
.btn-reject:hover { background: #DC2626; }

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

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php" class="active"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="send_notice.php"><i class="fa-solid fa-paper-plane"></i> <span>Send Notice</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="hod_profile.php" style="color:#94A3B8;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>Profile</span>
        </a>
        <a href="../auth/logout.php" style="color:#F87171;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Leave & OD Approvals</h1>
            <p>Review and act upon student and departmental staff leave applications</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-shield"></i> HOD Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($hod_name)?>
            </div>
        </div>
    </div>

    <div class="content-body">
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-clipboard-check"></i>
                </div>
                <div>
                    <h1>Pending & Recent Requests</h1>
                    <p>Total Requests: <b><?=count($requests_list)?></b> records on file</p>
                </div>
            </div>
            <div class="header-actions">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="leaveSearch" class="search-input" placeholder="Search applicant or date...">
                </div>
                <a href="dashboard_hod.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="approval-table" id="leaveTable">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Duration / Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests_list)): ?>
                            <tr><td colspan="7" style="text-align: center; color: #94A3B8; padding: 45px;">
                                <i class="fa-solid fa-folder-open" style="font-size:32px; margin-bottom:10px; display:block; color:#CBD5E1;"></i>
                                No leave or OD requests found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($requests_list as $req): 
                                $req_id = $req['id'];
                                $applicant = $req['applicant_name'] ?? 'N/A';
                                $app_role = $req['applicant_role'] ?? 'Student';
                                $type = $req['request_type'] ?? 'Leave';
                                $status = $req['status'] ?? 'Pending';
                                $dates = date('d M', strtotime($req['from_date'])) . ' - ' . date('d M Y', strtotime($req['to_date']));
                                $status_class = strtolower($status);
                            ?>
                            <tr>
                                <td>
                                    <strong style="color: #0F172A; font-size: 13.5px;"><?php echo htmlspecialchars($applicant); ?></strong><br>
                                    <small style="color: #64748B; font-family: monospace;">ID: <?php echo htmlspecialchars($req['applicant_id'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <span class="badge-role <?php echo strtolower($app_role) === 'faculty' ? 'role-faculty' : 'role-student'; ?>">
                                        <?php echo htmlspecialchars($app_role); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo strtolower($type) === 'od' ? 'type-od' : 'type-leave'; ?>">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td><strong style="color: #334155; font-size: 12.5px;"><?php echo $dates; ?></strong></td>
                                <td style="max-width: 220px; color: #475569; font-size: 12.5px;"><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo $status_class; ?>">
                                        <i class="fa-solid fa-circle" style="font-size: 6px;"></i> <?php echo strtoupper($status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($status === 'Pending'): ?>
                                        <button class="btn-act btn-approve" onclick="updateStatus(<?php echo $req_id; ?>, 'Approved')">
                                            <i class="fa-solid fa-check"></i> Approve
                                        </button>
                                        <button class="btn-act btn-reject" onclick="updateStatus(<?php echo $req_id; ?>, 'Rejected')">
                                            <i class="fa-solid fa-xmark"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-size: 12px; font-weight:600;"><i class="fa-solid fa-check-double"></i> Processed</span>
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
function updateStatus(requestId, newStatus) {
    if (!confirm('Are you sure you want to ' + newStatus + ' this request?')) return;

    fetch('leave_approvals.php', {
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

// Live search filter
document.getElementById('leaveSearch')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#leaveTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>