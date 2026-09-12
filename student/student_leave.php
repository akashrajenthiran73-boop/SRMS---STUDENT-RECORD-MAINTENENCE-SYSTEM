<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Check (Student Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if ($role !== 'Student') {
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied: Student Portal Only</h2>";
    exit();
}

$user_id   = $_SESSION['user_id'] ?? '';
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? 'Student';

// 2. Supabase Configuration
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// 3. Submit Leave / OD Request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    
    $request_type = trim($input['request_type'] ?? 'Leave');
    $send_to      = trim($input['send_to'] ?? 'Faculty'); // 'Faculty' or 'HOD'
    $from_date    = trim($input['from_date'] ?? '');
    $to_date      = trim($input['to_date'] ?? '');
    $reason       = trim($input['reason'] ?? '');

    if (empty($from_date) || empty($to_date) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields!']);
        exit();
    }

    $payload_data = [
        'applicant_name' => $user_name,
        'applicant_role' => 'Student',
        'applicant_id'   => strval($user_id),
        'request_type'   => $request_type,
        'from_date'      => $from_date,
        'to_date'        => $to_date,
        'reason'         => '[' . strtoupper($send_to) . '] ' . $reason,
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
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/leave_requests?applicant_id=eq." . urlencode($user_id) . "&applicant_role=eq.Student&order=id.desc";
    
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
<title>Leave & OD Application - Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Student Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
.sidebar-top { overflow-y: auto; padding: 22px 16px 10px 16px; }
.sidebar-top::-webkit-scrollbar { width: 4px; }
.sidebar-top::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }
.sidebar-brand { display: flex; align-items: center; gap: 12px; padding: 0 10px 22px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 18px; }
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #059669, #047857); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
.sidebar-brand h2 { font-size: 17px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.3px; }
.sidebar-brand span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.nav-category { font-size: 10.5px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.8px; padding: 14px 10px 6px 10px; }
.sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 3px; }
.sidebar-menu a { display: flex; align-items: center; gap: 12px; color: #94A3B8; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-menu a i { font-size: 14px; width: 18px; text-align: center; }
.sidebar-menu a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; transform: translateX(2px); }
.sidebar-menu a.active { background: #059669; color: #FFFFFF; font-weight: 600; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35); }
.sidebar-footer { padding: 14px 16px; border-top: 1px solid rgba(255, 255, 255, 0.08); background: rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 4px; }
.sidebar-footer a { display: flex; align-items: center; gap: 12px; padding: 9px 12px; text-decoration: none; font-size: 13px; font-weight: 500; border-radius: 9px; transition: all 0.2s ease; }
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header */
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
    gap: 14px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #ECFDF5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

/* Two-column Layout */
.leave-grid {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 24px;
    align-items: start;
}

@media (max-width: 1024px) {
    .leave-grid { grid-template-columns: 1fr; }
}

/* Card */
.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 1px solid #F1F5F9;
}

.card-title-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-title-left i {
    color: #059669;
}

/* Form Styles */
.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    font-size: 12.5px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

input[type="date"], select, textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 10px;
    font-size: 13.5px;
    outline: none;
    font-family: inherit;
    color: #0F172A;
    background: #FFFFFF;
    transition: all 0.2s ease;
}

input:focus, select:focus, textarea:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

textarea {
    resize: vertical;
    min-height: 90px;
    line-height: 1.5;
}

.dates-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.btn-submit {
    width: 100%;
    background: #059669;
    color: #FFFFFF;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
}

.btn-submit:hover {
    background: #047857;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
}

/* Table Design */
.table-wrapper {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
}

.req-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.req-table th {
    background: #F8FAFC;
    color: #475569;
    padding: 14px 16px;
    font-size: 11.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #E2E8F0;
}

.req-table td {
    padding: 14px 16px;
    font-size: 13.5px;
    border-bottom: 1px solid #F1F5F9;
    color: #1E293B;
}

.req-table tr:hover td {
    background: #F8FAFC;
}

.req-table tr:last-child td {
    border-bottom: none;
}

/* Badges */
.badge-type {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
}

.type-leave { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.type-od    { background: #E0E7FF; color: #3730A3; border: 1px solid #C7D2FE; }

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.status-pending  { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.status-approved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }
.status-rejected { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

@media(max-width: 900px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified Student Sidebar -->
<div class="sidebar">
    <div class="sidebar-top">
        <div class="sidebar-brand">
            <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <h2>SRMS Portal</h2>
                <span>Student Administration</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard_student.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-database"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>
            <li><a href="marks.php"><i class="fa-solid fa-award"></i> <span>Marks / Grades</span></a></li>

            <li class="nav-category">College & Campus</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievance.php"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php" class="active"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="student_profile.php" style="color:#94A3B8;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>My Profile</span>
        </a>
        <a href="../auth/logout.php" style="color:#F87171;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- Main Content Wrapper -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Leave & On-Duty Application</h1>
            <p>Submit formal absence or official on-duty requests</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($user_name)?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
                <div>
                    <h1>Student Leave & OD Requests</h1>
                    <p>Applications are routed directly to faculty advisors or HOD for digital approval</p>
                </div>
            </div>
        </div>

        <div class="leave-grid">
            
            <!-- Application Form -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Apply Leave / OD</span>
                    </div>
                </div>
                <form id="studentLeaveForm" onsubmit="submitStudentLeave(event)">
                    
                    <div class="form-group">
                        <label for="send_to">Send Request To</label>
                        <select id="send_to" required>
                            <option value="Faculty">Class Faculty Advisor</option>
                            <option value="HOD">Head of Department (HOD)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="request_type">Request Type</label>
                        <select id="request_type" required>
                            <option value="Leave">Casual Leave</option>
                            <option value="OD">On Duty (OD - Sports / Symposium / NCC)</option>
                        </select>
                    </div>

                    <div class="dates-row">
                        <div class="form-group">
                            <label for="from_date">From Date</label>
                            <input type="date" id="from_date" required>
                        </div>
                        <div class="form-group">
                            <label for="to_date">To Date</label>
                            <input type="date" id="to_date" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason / Purpose</label>
                        <textarea id="reason" placeholder="Enter specific reason, event details, or circumstances..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Send Application
                    </button>
                </form>
            </div>

            <!-- My Applied Requests History -->
            <div class="card">
                <div class="card-title">
                    <div class="card-title-left">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Application History</span>
                    </div>
                    <span style="font-size: 12px; font-weight: 700; color: #059669; background: #ECFDF5; padding: 4px 10px; border-radius: 20px;">
                        <?=count($my_requests)?> Requests
                    </span>
                </div>
                
                <div class="table-wrapper">
                    <table class="req-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($my_requests)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #94A3B8; padding: 30px;">No leave or OD applications submitted yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($my_requests as $req): 
                                    $type = $req['request_type'] ?? 'Leave';
                                    $status = $req['status'] ?? 'Pending';
                                    $dates = date('d M', strtotime($req['from_date'])) . ' - ' . date('d M Y', strtotime($req['to_date']));
                                    $status_class = 'status-' . strtolower($status);
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge-type <?php echo strtolower($type) === 'od' ? 'type-od' : 'type-leave'; ?>">
                                            <i class="fa-solid <?php echo strtolower($type) === 'od' ? 'fa-briefcase' : 'fa-calendar-minus'; ?>"></i>
                                            <?php echo htmlspecialchars($type); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $dates; ?></strong></td>
                                    <td style="max-width: 220px; line-height: 1.4;"><?php echo htmlspecialchars($req['reason']); ?></td>
                                    <td>
                                        <span class="status-pill <?=$status_class?>">
                                            <i class="fa-solid <?php echo strtolower($status) === 'approved' ? 'fa-circle-check' : (strtolower($status) === 'rejected' ? 'fa-circle-xmark' : 'fa-clock'); ?>"></i>
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
</div>

<script>
function submitStudentLeave(e) {
    e.preventDefault();

    const sendTo      = document.getElementById('send_to').value;
    const requestType = document.getElementById('request_type').value;
    const fromDate    = document.getElementById('from_date').value;
    const toDate      = document.getElementById('to_date').value;
    const reason      = document.getElementById('reason').value;

    fetch('student_leave.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            send_to: sendTo,
            request_type: requestType,
            from_date: fromDate,
            to_date: toDate,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Request sent successfully to ' + sendTo + '!');
            window.location.reload();
        } else {
            alert('Error submitting request: ' + (data.message || JSON.stringify(data.error)));
        }
    })
    .catch(err => {
        console.error("Full Error Details:", err);
        alert('Server Connection Error! Check Console (F12) or Network tab for details.');
    });
}
</script>

</body>
</html>