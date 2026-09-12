<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  // SRMS/.env (Root Folder)
    __DIR__ . '/.env',                     // SRMS/HOD/.env
    $_SERVER['DOCUMENT_ROOT'] . '/SRMS/.env'
];

foreach ($possible_env_paths as $path) {
    if (file_exists($path)) {
        $parsed = @parse_ini_file($path);
        if ($parsed) {
            $env = $parsed;
            break;
        }
    }
}

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'HOD'){
    header("Location: ../auth/login.php"); 
    exit;
}

// APPROVE LOGIC
if(isset($_GET['approve_reg'])){
    $id = $_GET['approve_reg'];
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?id=eq.$id";
    $data = json_encode(['status' => 'Approved']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);
    header("Location: bio_data_list.php?msg=approved"); 
    exit;
}

// BIO_DATA FETCH
$bio_list = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/bio_data?select=*&order=created_at.desc";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["apikey: $SUPABASE_KEY", "Authorization: Bearer $SUPABASE_KEY"]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode($res, true);
    if(is_array($decoded)) {
        $bio_list = $decoded;
    }
}

require_once __DIR__ . '/../includes/college_data.php';
$session_dept = $_SESSION['department'] ?? 'CS';
if (empty($session_dept) || $session_dept === 'BSC') $session_dept = 'CS';

$selected_year = $_GET['year'] ?? 'All';

// 1. Filter by HOD Department
if (!empty($bio_list)) {
    $bio_list = array_filter($bio_list, function($b) use ($session_dept) {
        $d = strtoupper($b['department'] ?? '');
        if ($session_dept === 'CS') {
            return (strpos($d, 'CS') !== false || strpos($d, 'COMPUTER') !== false || empty($d));
        }
        return (strpos($d, strtoupper($session_dept)) !== false);
    });
}

// 2. Filter by Academic Year
if ($selected_year !== 'All' && !empty($bio_list)) {
    $bio_list = array_filter($bio_list, function($b) use ($selected_year) {
        $info = get_student_year_info($b);
        return ($info['filter_tag'] === $selected_year || $info['level'] === $selected_year);
    });
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
<title>Bio Data Records - HOD Portal</title>
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

.msg { 
    background: #ECFDF5; 
    border: 1px solid #A7F3D0;
    padding: 14px 20px; 
    border-radius: 12px; 
    margin-bottom: 24px; 
    color: #065F46; 
    font-weight: 600;
    font-size: 13.5px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
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

table { 
    width: 100%; 
    border-collapse: collapse; 
    text-align: left; 
}

th, td { 
    padding: 15px 20px; 
    font-size: 13px;
}

th { 
    background: #F8FAFC; 
    color: #475569; 
    font-weight: 700;
    border-bottom: 1px solid #E2E8F0;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.6px;
    white-space: nowrap;
}

td { 
    border-bottom: 1px solid #F1F5F9; 
    color: #334155;
    vertical-align: middle;
}

tr:last-child td {
    border-bottom: none;
}

tbody tr:hover td {
    background: #F8FAFC;
}

.student-name-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.student-avatar {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #F3E8FF;
    color: #7C3AED;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.reg-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-family: monospace;
    font-weight: 700;
    font-size: 12px;
    color: #0F172A;
}

.status-pill { 
    padding: 4px 10px; 
    border-radius: 20px; 
    font-size: 11.5px; 
    font-weight: 700; 
    text-transform: capitalize; 
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.status-pill.Pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
.status-pill.Approved { background: #ECFDF5; color: #047857; border: 1px solid #A7F3D0; }

.btn-action { 
    padding: 6px 12px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-size: 12px; 
    font-weight: 600; 
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-view { background: #F1F5F9; color: #334155; border: 1px solid #CBD5E1; }
.btn-view:hover { background: #E2E8F0; color: #0F172A; }

.btn-approve { background: linear-gradient(135deg, #7C3AED, #6D28D9); color: white; }
.btn-approve:hover { opacity: 0.92; transform: translateY(-1px); }

.approved-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #059669;
    font-weight: 700;
    font-size: 12.5px;
    margin-right: 6px;
}

@media print {
    .sidebar, .topbar, .btn-back, .search-box, .btn-action, .header-actions { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .table-card { border: none !important; box-shadow: none !important; }
}

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
            <li><a href="bio_data_list.php" class="active"><i class="fa-solid fa-address-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis_form_list.php"><i class="fa-solid fa-database"></i> <span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-line"></i> <span>Result Analysis</span></a></li>

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> <span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievances.php"><i class="fa-solid fa-headset"></i> <span>Student Grievances</span></a></li>

            <li class="nav-category">Department Admin</li>
            <li><a href="leave_approvals.php"><i class="fa-solid fa-clipboard-check"></i> <span>Leave Approvals</span></a></li>
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
            <h1>Bio Data Management</h1>
            <p>Review, verify and approve student personal bio-data submissions</p>
        </div>
        <div class="user-profile">
            <span style="background: #F1F5F9; border: 1px solid #CBD5E1; color: #334155; font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-building-columns" style="color:#7C3AED;"></i> B.Sc CS Dept
            </span>
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
                    <i class="fa-solid fa-address-card"></i>
                </div>
                <div>
                    <h1>Bio Data Records & Approvals</h1>
                    <p>Total Records: <b><?=count($bio_list)?></b> students registered</p>
                </div>
            </div>
            <div class="header-actions">
                <form method="GET" action="bio_data_list.php" style="display: inline-flex; align-items: center; gap: 8px;">
                    <span style="font-size: 12px; font-weight: 700; color: #475569;"><i class="fa-solid fa-graduation-cap" style="color: #7C3AED;"></i> Class:</span>
                    <select name="year" onchange="this.form.submit()" style="padding: 7px 12px; border: 1.5px solid #E2E8F0; border-radius: 8px; font-size: 12.5px; font-weight: 600; outline: none; background: #FFFFFF;">
                        <option value="All" <?=$selected_year === 'All' ? 'selected' : ''?>>All Years</option>
                        <option value="UG_1" <?=$selected_year === 'UG_1' ? 'selected' : ''?>>UG - 1st Year (I Year)</option>
                        <option value="UG_2" <?=$selected_year === 'UG_2' ? 'selected' : ''?>>UG - 2nd Year (II Year)</option>
                        <option value="UG_3" <?=$selected_year === 'UG_3' ? 'selected' : ''?>>UG - 3rd Year (III Year)</option>
                        <option value="PG_1" <?=$selected_year === 'PG_1' ? 'selected' : ''?>>PG - 1st Year (I PG)</option>
                        <option value="PG_2" <?=$selected_year === 'PG_2' ? 'selected' : ''?>>PG - 2nd Year (II PG)</option>
                    </select>
                </form>
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="bioSearch" class="search-input" placeholder="Search student or reg...">
                </div>
                <a href="dashboard_hod.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div class="msg"><i class="fa-solid fa-circle-check"></i> Bio Data Approved Successfully!</div>
        <?php endif; ?>

        <div class="table-card">
            <div class="table-responsive">
                <table id="bioTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tamil Reg No</th>
                            <th>Student Name</th>
                            <th>Academic Class</th>
                            <th>Father / Parent Name</th>
                            <th>Department</th>
                            <th>DOB</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(is_array($bio_list) && count($bio_list) > 0): $i=1; foreach($bio_list as $b): 
                        $raw_status = trim($b['status'] ?? 'Pending');
                        $status = ucfirst(strtolower(trim($raw_status, "'\" ")));
                        if(empty($status)) { $status = 'Pending'; }

                        $yinfo = get_student_year_info($b);
                        $name = htmlspecialchars($b['name_ta_en'] ?? '-');
                        $fname = htmlspecialchars($b['parents_name_ta'] ?? '-');
                        $dept = htmlspecialchars($b['department'] ?? '-');
                        $dob = htmlspecialchars($b['dob'] ?? '-');
                        $reg_id = $b['id'] ?? '';
                        $initial = strtoupper(substr($name, 0, 1));
                    ?>
                    <tr>
                        <td style="font-weight: 600; color: #94A3B8;"><?=$i++?></td>
                        <td><span class="reg-badge"><?=htmlspecialchars($b['tamil_reg_no'] ?? '-')?></span></td>
                        <td>
                            <div class="student-name-cell">
                                <div class="student-avatar"><?=$initial !== '-' ? $initial : 'B'?></div>
                                <span style="font-weight: 600; color: #0F172A;"><?=$name?></span>
                            </div>
                        </td>
                        <td>
                            <span style="background: #F3E8FF; color: #7C3AED; font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; border: 1px solid #E9D5FF; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa-solid <?=$yinfo['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i> <?=htmlspecialchars($yinfo['short'])?>
                            </span>
                        </td>
                        <td><?=$fname?></td>
                        <td><?=$dept?></td>
                        <td style="color: #64748B; font-size: 12.5px;"><?=$dob?></td>
                        <td>
                            <span class="status-pill <?=$status?>">
                                <i class="fa-solid fa-circle" style="font-size: 6px;"></i> <?=$status?>
                            </span>
                        </td>
                        <td>
                            <?php if($status === 'Pending'):?>
                                <a href="bio_data_list.php?approve_reg=<?=$reg_id?>" class="btn-action btn-approve" onclick="return confirm('Approve <?=$name?>?')"><i class="fa-solid fa-check"></i> Approve</a>
                            <?php else:?>
                                <span class="approved-tag"><i class="fa-solid fa-circle-check"></i> Approved</span>
                            <?php endif;?>
                            <a href="view_bio.php?id=<?=$reg_id?>" class="btn-action btn-view"><i class="fa-solid fa-eye"></i> View</a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:50px 20px; color:#94A3B8;">
                            <i class="fa-solid fa-folder-open" style="font-size:32px; margin-bottom:10px; display:block; color:#CBD5E1;"></i>
                            No bio data records found.
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Live search filter
document.getElementById('bioSearch')?.addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#bioTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

</body>
</html>