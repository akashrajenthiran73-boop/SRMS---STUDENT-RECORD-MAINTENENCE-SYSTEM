<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check (Faculty Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if (!in_array($role, ['Faculty', 'HOD', 'Admin', 'Super Admin'])) {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied: Faculty Only Page</h2>";
    exit();
}

$user_id   = $_SESSION['user_id'] ?? '';
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['email'] ?? 'Faculty Member';

// 2. Supabase Configuration
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// 3. Submit Leave / OD Request Logic (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    
    $request_type = trim($input['request_type'] ?? 'Leave');
    $from_date    = trim($input['from_date'] ?? '');
    $to_date      = trim($input['to_date'] ?? '');
    $reason       = trim($input['reason'] ?? '');

    if (empty($from_date) || empty($to_date) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields!']);
        exit();
    }

    $payload_data = [
        'applicant_name' => $user_name,
        'applicant_role' => 'Faculty',
        'applicant_id'   => strval($user_id),
        'request_type'   => $request_type,
        'from_date'      => $from_date,
        'to_date'        => $to_date,
        'reason'         => $reason,
        'status'         => 'Pending'
    ];

    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 201 || $http_code == 200) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $response]);
    }
    exit();
}

// 4. Fetch My Applied Leave Requests
$my_requests = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests?applicant_id=eq." . urlencode($user_id) . "&applicant_role=eq.Faculty&order=id.desc";
    
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

    $my_requests = json_decode($res, true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave & OD - Faculty Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-space; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #2563EB; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }

/* Main Content */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; letter-spacing: 0.3px; }

/* Page Layout */
.content-body { padding: 30px 36px; display: grid; grid-template-columns: 1fr 1.6fr; gap: 26px; }
@media (max-width: 1024px) {
    .content-body { grid-template-columns: 1fr; }
}

.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #F1F5F9; }
.card-title { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: #0F172A; }
.card-title i { color: #2563EB; }

/* Form Styles */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 13.5px; outline: none; font-family: inherit; color: #0F172A; background: #FFFFFF; transition: all 0.2s; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.form-group textarea { resize: vertical; min-height: 95px; }

.btn-submit { width: 100%; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; border: none; padding: 12px; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-submit:hover { opacity: 0.95; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }

/* Table Wrapper */
.table-wrapper { overflow-x: auto; border: 1px solid #E2E8F0; border-radius: 12px; }
.req-table { width: 100%; border-collapse: collapse; text-align: left; }
.req-table th { background: #F8FAFC; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; padding: 12px 14px; border-bottom: 1px solid #E2E8F0; }
.req-table td { padding: 13px 14px; font-size: 13px; border-bottom: 1px solid #F1F5F9; color: #334155; }
.req-table tr:last-child td { border-bottom: none; }
.req-table tr:hover td { background-color: #F8FAFC; }

/* Badges */
.badge-type { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; }
.type-leave { background: #FEF2F2; color: #DC2626; border: 1px solid #FEE2E2; }
.type-od { background: #EEF2FF; color: #4338CA; border: 1px solid #E0E7FF; }

.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.status-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.status-approved { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
.status-rejected { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
</style>
</head>
<body>

<!-- Unified Faculty Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Faculty Panel</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> Students Record</a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> Bio Data</a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-database"></i> UMIS Data</a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> Result Analysis</a></li>

            <li class="nav-category">Faculty Panel</li>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-envelope-open-text"></i> Student Leave Requests</a></li>
            <li><a href="leave_faculty.php" class="active"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-file-pdf"></i> Syllabus & Materials</a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-tasks"></i> Assignments</a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> Announcements</a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="faculty_profile.php" class="sidebar-menu" style="display:flex; align-items:center; gap:12px; color:#94A3B8; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> Profile
        </a>
        <a href="../auth/logout.php" style="display:flex; align-items:center; gap:12px; color:#F87171; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:500; border-radius:9px;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> Logout
        </a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Leave & On-Duty (OD) Application</h1>
            <p>Submit leave/OD requests directly to HOD and track approval status</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-tie"></i> Faculty Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <!-- Leave Application Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Apply for Leave / OD</span>
                </div>
            </div>
            <form id="leaveForm" onsubmit="submitLeaveRequest(event)">
                <div class="form-group">
                    <label for="request_type"><i class="fa-solid fa-tags" style="color: #64748B;"></i> Application Type</label>
                    <select id="request_type" required>
                        <option value="Leave">Casual / Medical Leave</option>
                        <option value="OD">On Duty (OD)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                    <div class="form-group">
                        <label for="from_date"><i class="fa-regular fa-calendar" style="color: #64748B;"></i> From Date</label>
                        <input type="date" id="from_date" required>
                    </div>
                    <div class="form-group">
                        <label for="to_date"><i class="fa-regular fa-calendar-check" style="color: #64748B;"></i> To Date</label>
                        <input type="date" id="to_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reason"><i class="fa-solid fa-align-left" style="color: #64748B;"></i> Reason for Leave / OD</label>
                    <textarea id="reason" placeholder="Mention the specific purpose or reason..." required></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Submit to HOD
                </button>
            </form>
        </div>

        <!-- History & Status List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>My Request History</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 500;">
                    Total Requests: <?php echo count($my_requests); ?>
                </span>
            </div>
            
            <div class="table-wrapper">
                <table class="req-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Duration / Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_requests)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94A3B8; padding: 32px 16px;">
                                    <i class="fa-regular fa-folder-open" style="font-size: 26px; display: block; margin-bottom: 8px; color: #CBD5E1;"></i>
                                    No leave or OD requests submitted yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($my_requests as $req): 
                                $type = $req['request_type'] ?? 'Leave';
                                $status = $req['status'] ?? 'Pending';
                                $dates = date('d M', strtotime($req['from_date'])) . ' - ' . date('d M Y', strtotime($req['to_date']));
                                $status_class = strtolower($status) === 'approved' ? 'status-approved' : (strtolower($status) === 'rejected' ? 'status-rejected' : 'status-pending');
                                $status_icon = strtolower($status) === 'approved' ? 'fa-circle-check' : (strtolower($status) === 'rejected' ? 'fa-circle-xmark' : 'fa-hourglass-half');
                            ?>
                            <tr>
                                <td>
                                    <span class="badge-type <?php echo strtolower($type) === 'od' ? 'type-od' : 'type-leave'; ?>">
                                        <i class="<?php echo strtolower($type) === 'od' ? 'fa-solid fa-briefcase' : 'fa-solid fa-calendar-xmark'; ?>"></i>
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td><strong style="color: #0F172A;"><?php echo $dates; ?></strong></td>
                                <td style="max-width: 220px; line-height: 1.4;"><?php echo htmlspecialchars($req['reason']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fa-solid <?php echo $status_icon; ?>"></i>
                                        <?php echo strtoupper($status); ?>
                                    </span>
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
function submitLeaveRequest(e) {
    e.preventDefault();

    const requestType = document.getElementById('request_type').value;
    const fromDate    = document.getElementById('from_date').value;
    const toDate      = document.getElementById('to_date').value;
    const reason      = document.getElementById('reason').value;

    fetch('leave_faculty.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            request_type: requestType,
            from_date: fromDate,
            to_date: toDate,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Leave/OD request submitted successfully to HOD!');
            window.location.reload();
        } else {
            alert('Error submitting request: ' + (data.message || JSON.stringify(data.error)));
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