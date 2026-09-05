<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication & Role setup
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role']; // 'Student', 'Faculty', 'HOD' / 'Admin' / 'Super Admin'
$can_edit = in_array($role, ['HOD', 'Admin', 'Super Admin', 'Faculty']);
$is_hod = in_array($role, ['HOD', 'Admin', 'Super Admin']);

// 2. Load Supabase Environment Variables
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// 3. Handle AJAX Save Request (Inline Backend API Handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $day = $input['day'] ?? '';
    $hour = $input['hour'] ?? 1;
    $subject = $input['subject'] ?? '';
    $span = $input['span'] ?? 1;

    $url = $SUPABASE_URL . "/rest/v1/timetable?on_conflict=day_order,hour_slot";
    $payload = json_encode([
        'day_order' => $day,
        'hour_slot' => (int)$hour,
        'subject_code' => $subject,
        'colspan' => (int)$span
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: resolution=merge-duplicates"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 || $http_code == 201) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $response]);
    }
    exit();
}

// 4. Default Timetable Structure (Fallback Data)
$timetable = [
    'I'   => [['sub' => 'SE(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 3], ['sub' => 'PROJ-VIVA(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'V.Edu(SD)', 'span' => 1, 'hour' => 5]],
    'II'  => [['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(ACA)', 'span' => 1, 'hour' => 2], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 3], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 4], ['sub' => 'PROJ-VIVA(RM)', 'span' => 1, 'hour' => 5]],
    'III' => [['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(SD)', 'span' => 1, 'hour' => 2], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'Tnskill-SALESFORCE', 'span' => 1, 'hour' => 5]],
    'IV'  => [['sub' => 'SE(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS LAB(GKM)', 'span' => 2, 'hour' => 4]],
    'V'   => [['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'Tnskill-SALESFORCE', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'V.Edu(SD)', 'span' => 1, 'hour' => 5]],
    'VI'  => [['sub' => 'DBMS LAB(GKM)', 'span' => 3, 'hour' => 1], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 5]]
];

// 5. Fetch Real-time Timetable Data from Supabase
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $fetch_url = $SUPABASE_URL . "/rest/v1/timetable?select=*&order=day_order,hour_slot";
    $ch = curl_init($fetch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ]);
    $db_response = curl_exec($ch);
    curl_close($ch);

    $db_data = json_decode($db_response, true);

    if (is_array($db_data) && !empty($db_data)) {
        $db_timetable = ['I' => [], 'II' => [], 'III' => [], 'IV' => [], 'V' => [], 'VI' => []];
        foreach ($db_data as $row) {
            $day = $row['day_order'];
            if (isset($db_timetable[$day])) {
                $db_timetable[$day][] = [
                    'sub' => $row['subject_code'],
                    'span' => (int)$row['colspan'],
                    'hour' => (int)$row['hour_slot']
                ];
            }
        }
        // Filter out empty days to use dynamic db data
        foreach ($db_timetable as $d => $slots) {
            if (!empty($slots)) {
                $timetable[$d] = $slots;
            }
        }
    }
}
$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Class Timetable - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar {
    width: 260px;
    background: #0B132B;
    color: #FFFFFF;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 100;
    justify-content: space-between;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}
.sidebar-content { overflow-y: auto; flex: 1; }
.sidebar-header {
    padding: 24px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.sidebar-header .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #FFFFFF;
    box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
}
.sidebar-header h2 { font-size: 16px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.2px; }
.sidebar-header span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.sidebar-menu { list-style: none; padding: 16px 12px; }
.sidebar-menu li { margin-bottom: 3px; }
.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #94A3B8;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}
.sidebar-menu a.active {
    background: #059669;
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}
.sidebar-menu a i { width: 18px; text-align: center; font-size: 14px; }
.nav-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748B;
    padding: 14px 14px 6px;
    font-weight: 700;
}
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    transition: 0.2s;
}
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }

/* Main Content */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    border-bottom: 1px solid #E2E8F0;
    padding: 16px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 90;
}
.topbar-title h1 { font-size: 20px; font-weight: 800; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header Card */
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
.page-header h2 {
    font-size: 19px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}
.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

/* Action Buttons */
.action-btns { display: flex; gap: 10px; }
.btn {
    border: none;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    text-decoration: none;
}
.btn-print {
    background: #F1F5F9;
    color: #334155;
    border: 1px solid #CBD5E1;
}
.btn-print:hover {
    background: #E2E8F0;
    color: #0F172A;
}
.btn-approve {
    background: #059669;
    color: white;
}
.btn-approve:hover {
    background: #047857;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

/* Main Card */
.card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

/* Timetable Info Header */
.schedule-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 12px;
}
.schedule-banner-left {
    display: flex;
    align-items: center;
    gap: 12px;
}
.schedule-banner-left h3 {
    font-size: 15px;
    font-weight: 700;
    color: #0F172A;
}
.schedule-banner-left span {
    font-size: 12.5px;
    color: #64748B;
}
.schedule-tag {
    background: #ECFDF5;
    color: #059669;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    border: 1px solid #A7F3D0;
}

/* Timetable Grid Design */
.table-wrapper {
    overflow-x: auto;
    border: 1px solid #CBD5E1;
    border-radius: 12px;
    background: #FFFFFF;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.timetable {
    width: 100%;
    border-collapse: collapse;
    text-align: center;
    background: white;
}
.timetable th, .timetable td {
    border: 1px solid #E2E8F0;
    padding: 16px 12px;
    font-size: 13.5px;
    font-weight: 600;
}
.timetable thead th {
    background: #F1F5F9;
    color: #1E293B;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.timetable thead th .period-time {
    display: block;
    font-size: 11px;
    color: #64748B;
    font-weight: 500;
    margin-top: 3px;
    text-transform: none;
    letter-spacing: normal;
}
.timetable .day-col {
    background: #F8FAFC;
    color: #0F172A;
    font-weight: 800;
    width: 110px;
    border-right: 2px solid #CBD5E1;
    font-size: 14px;
}
.timetable .day-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    background: #E2E8F0;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

/* Dynamic cell styling */
.timetable td.editable {
    cursor: pointer;
    position: relative;
    transition: background 0.15s ease;
}
.timetable td.editable:hover {
    background: #FEF3C7 !important;
}
.timetable td.lab-cell {
    background: #EFF6FF;
    color: #1D4ED8;
    font-weight: 700;
    border-color: #BFDBFE;
}
.timetable td.lab-cell::before {
    content: "⚡ LAB SESSION: ";
    font-size: 10px;
    font-weight: 800;
    display: block;
    color: #2563EB;
    margin-bottom: 2px;
    letter-spacing: 0.5px;
}

/* Modal Edit Box */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    padding: 28px;
    border-radius: 16px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid #E2E8F0;
}
.modal-content h3 {
    margin-bottom: 16px;
    color: #0F172A;
    font-size: 17px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-content h3 i { color: #059669; }
.modal-content label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin: 12px 0 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.modal-content input, .modal-content select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 13.5px;
    font-family: inherit;
    outline: none;
    transition: 0.2s;
}
.modal-content input:focus, .modal-content select:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

@media print {
    .sidebar, .topbar, .action-btns, .role-badge, .schedule-tag { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
    .table-wrapper { border: 1px solid #000 !important; }
    .timetable th, .timetable td { border: 1px solid #000 !important; color: #000 !important; }
    .timetable thead th { background: #eee !important; }
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
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

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php" class="active"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
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

<!-- MAIN CONTENT -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Academic Timetable</h1>
            <p>Weekly class schedule and laboratory sessions</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-shield-halved"></i> <?php echo htmlspecialchars($role); ?>
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-calendar-days"></i>
                </div>
                <div>
                    <h2>III B.Sc Computer Science - Schedule</h2>
                    <p>Academic Year 2026 - 2027 | Odd Semester | 6-Day Order Pattern</p>
                </div>
            </div>
            <div class="action-btns">
                <button class="btn btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Schedule</button>
                <?php if ($is_hod): ?>
                    <button class="btn btn-approve" onclick="alert('Timetable Approved & Published Successfully!')"><i class="fa-solid fa-circle-check"></i> Approve & Publish</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="schedule-banner">
                <div class="schedule-banner-left">
                    <i class="fa-solid fa-clock" style="color: #059669;"></i>
                    <div>
                        <h3>Standard Class Timings</h3>
                        <span>Morning: 09:30 AM - 12:45 PM | Afternoon: 01:30 PM - 03:45 PM</span>
                    </div>
                </div>
                <div class="schedule-tag">
                    <i class="fa-solid fa-circle-dot" style="font-size: 8px;"></i> Active Semester
                </div>
            </div>

            <!-- TIMETABLE TABLE -->
            <div class="table-wrapper">
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="day-col">Day Order</th>
                            <th>
                                Period 1
                                <span class="period-time">09:30 - 10:30</span>
                            </th>
                            <th>
                                Period 2
                                <span class="period-time">10:30 - 11:30</span>
                            </th>
                            <th>
                                Period 3
                                <span class="period-time">11:45 - 12:45</span>
                            </th>
                            <th>
                                Period 4
                                <span class="period-time">01:30 - 02:30</span>
                            </th>
                            <th>
                                Period 5
                                <span class="period-time">02:30 - 03:45</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable as $day => $slots): ?>
                            <tr>
                                <td class="day-col"><span class="day-badge">Day <?php echo $day; ?></span></td>
                                <?php foreach ($slots as $slot): ?>
                                    <td colspan="<?php echo $slot['span']; ?>" 
                                        class="<?php echo ($slot['span'] > 1) ? 'lab-cell' : ''; ?> <?php echo $can_edit ? 'editable' : ''; ?>"
                                        <?php if ($can_edit): ?> onclick="openEditModal('<?php echo $day; ?>', <?php echo $slot['hour']; ?>, '<?php echo htmlspecialchars($slot['sub'], ENT_QUOTES); ?>', <?php echo $slot['span']; ?>)" <?php endif; ?>>
                                        <?php echo htmlspecialchars($slot['sub']); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- HOD / FACULTY EDIT MODAL -->
<?php if ($can_edit): ?>
<div class="modal" id="editModal">
    <div class="modal-content">
        <h3><i class="fa-solid fa-pen-to-square"></i> Edit Schedule Slot</h3>
        <form id="editForm" onsubmit="event.preventDefault(); saveSlot();">
            <input type="hidden" id="modal_day" name="day">
            <input type="hidden" id="modal_hour" name="hour">
            
            <label>Subject & Staff Code</label>
            <input type="text" id="modal_subject" name="subject" required placeholder="e.g. DBMS(GKM)">

            <?php if ($is_hod): ?>
            <label>Span (Hours Count)</label>
            <select id="modal_span" name="span">
                <option value="1">1 Hour (Single Period)</option>
                <option value="2">2 Hours (Lab / Block)</option>
                <option value="3">3 Hours (Full Session)</option>
            </select>
            <?php else: ?>
                <input type="hidden" id="modal_span" value="1">
            <?php endif; ?>

            <div class="modal-footer">
                <button type="button" class="btn btn-print" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-approve">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openEditModal(day, hour, subject, span) {
    document.getElementById('modal_day').value = day;
    document.getElementById('modal_hour').value = hour;
    document.getElementById('modal_subject').value = subject;
    if(document.getElementById('modal_span')) {
        document.getElementById('modal_span').value = span;
    }
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function saveSlot() {
    const day = document.getElementById('modal_day').value;
    const hour = document.getElementById('modal_hour').value;
    const subject = document.getElementById('modal_subject').value;
    const span = document.getElementById('modal_span') ? document.getElementById('modal_span').value : 1;

    fetch('timetable.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ day: day, hour: hour, subject: subject, span: span })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('Timetable Slot Updated Successfully!');
            location.reload();
        } else {
            alert('Error updating slot: ' + (data.message || data.error));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server connection error!');
    });
}
</script>

</body>
</html>