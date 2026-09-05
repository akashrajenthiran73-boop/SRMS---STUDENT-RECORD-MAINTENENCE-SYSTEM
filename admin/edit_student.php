<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Session Auth Check (Allow Admin & Super Admin)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) { 
    header("Location: ../auth/login.php"); 
    exit; 
}

$display_role = $_SESSION['role'];

// 2. Multi-location Safe .env File Loader
$env_path = __DIR__ . '/../.env';
if (!file_exists($env_path)) {
    die("Error: .env file not found at " . realpath(__DIR__ . '/..'));
}

$env = parse_ini_file($env_path);
$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');

if (!isset($_GET['id'])) { 
    header("Location: student_records.php"); 
    exit; 
}

$id = $_GET['id'];

// Fetch Existing Student Record
$url = $SUPABASE_URL . "/rest/v1/students?id=eq.$id&select=*";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $SUPABASE_KEY", 
    "Authorization: Bearer $SUPABASE_KEY"
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (!$result || count($result) == 0) {
    die("Student record not found!");
}
$student = $result[0];

// Handle Update Submit
if (isset($_POST['update'])) {
    $data = $_POST;
    unset($data['update']);

    // Process Dynamic Semester 1 to 6 Marks into JSON String
    for ($sem = 1; $sem <= 6; $sem++) {
        $marks_data = [];
        if (isset($_POST["sem{$sem}_subject"]) && is_array($_POST["sem{$sem}_subject"])) {
            $subjects = $_POST["sem{$sem}_subject"];
            $ues = $_POST["sem{$sem}_ue"] ?? [];
            $ias = $_POST["sem{$sem}_ia"] ?? [];
            $totals = $_POST["sem{$sem}_total"] ?? [];
            $pfs = $_POST["sem{$sem}_pf"] ?? [];

            for ($i = 0; $i < count($subjects); $i++) {
                if (!empty(trim($subjects[$i]))) {
                    $marks_data[] = [
                        'subject'   => trim($subjects[$i]),
                        'ue'        => $ues[$i] ?? 0,
                        'ia'        => $ias[$i] ?? 0,
                        'total'     => $totals[$i] ?? 0,
                        'pass_fail' => $pfs[$i] ?? ''
                    ];
                }
            }
        }
        $data["sem{$sem}_marks"] = json_encode($marks_data);

        // Remove raw form array fields so they don't cause SQL errors
        unset($data["sem{$sem}_subject"]);
        unset($data["sem{$sem}_ue"]);
        unset($data["sem{$sem}_ia"]);
        unset($data["sem{$sem}_total"]);
        unset($data["sem{$sem}_pf"]);
    }

    // Convert empty numeric fields to NULL
    if (empty($data['days_present'])) $data['days_present'] = null;
    if (empty($data['working_days'])) $data['working_days'] = null;
    if (empty($data['class_rank'])) $data['class_rank'] = null;

    $update_url = $SUPABASE_URL . "/rest/v1/students?id=eq.$id";
    $ch = curl_init($update_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY", 
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json", 
        "Prefer: return=representation"
    ]);
    curl_exec($ch);
    curl_close($ch);
    
    header("Location: student_records.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Student - <?php echo htmlspecialchars($student['name'] ?? ''); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #1E293B;
    display: flex;
    min-height: 100vh;
}

/* Sidebar Navigation */
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
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    display: block;
    margin-top: 4px;
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

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 11px 16px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
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

.menu-divider {
    margin: 14px 10px;
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

/* Main Content Area */
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
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar h1 {
    color: #1E3A8A;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.3px;
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
.user-profile-badge i {
    color: #2563EB;
}

.content-body {
    padding: 35px 40px;
    flex: 1;
}

/* Form Container Card */
.form-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 32px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.form-header-title {
    font-size: 20px;
    color: #1E3A8A;
    font-weight: 800;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 18px;
    border-bottom: 1.5px solid #F1F5F9;
}

.form-header-title .title-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Section Cards */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 24px;
    background: #FFFFFF;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.01);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1.5px solid #F1F5F9;
}

.section h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #2563EB;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #475569;
    margin-bottom: 6px;
}

input, textarea, select {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    font-size: 13.5px;
    color: #1E293B;
    background: #FFFFFF;
    outline: none;
    transition: all 0.2s ease;
}

input:focus, textarea:focus, select:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

/* Marks Table */
.marks-table-wrapper {
    overflow-x: auto;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    margin-top: 10px;
}

.marks-table {
    width: 100%;
    border-collapse: collapse;
}

.marks-table th, .marks-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #F1F5F9;
    text-align: center;
}

.marks-table th {
    background: #F8FAFC;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1.5px solid #E2E8F0;
}

.marks-table input {
    width: 100%;
    padding: 8px 10px;
    border: 1.5px solid #E2E8F0;
    border-radius: 6px;
    font-size: 13px;
    text-align: center;
}

.marks-table input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}

.btn-add {
    background: #10B981;
    color: white;
    border: none;
    padding: 7px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.btn-add:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-delete {
    background: #FEE2E2;
    color: #DC2626;
    border: 1px solid #FECACA;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
}
.btn-delete:hover {
    background: #DC2626;
    color: #FFFFFF;
}

.pf-pass {
    color: #059669 !important;
    font-weight: 700 !important;
    background: #ECFDF5 !important;
    border-color: #A7F3D0 !important;
}

.pf-fail {
    color: #DC2626 !important;
    font-weight: 700 !important;
    background: #FEF2F2 !important;
    border-color: #FECACA !important;
}

/* Action Buttons */
.actions {
    display: flex !important;
    flex-direction: row;
    gap: 14px;
    margin-top: 30px;
    border-top: 1.5px solid #F1F5F9;
    padding-top: 24px;
    width: 100%;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.25s ease;
}

.btn-update {
    background: #10B981;
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
}
.btn-update:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
}

.btn-cancel {
    background: #FFFFFF;
    color: #475569;
    border: 1.5px solid #E2E8F0;
}
.btn-cancel:hover {
    background: #F8FAFC;
    color: #0F172A;
    border-color: #CBD5E1;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .grid-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Arignar Anna College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php" class="active"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
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
        <h1>Edit Student Record</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($display_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="form-card">
            
            <div class="form-header-title">
                <i class="fa-solid fa-pen-to-square" style="color: #2563EB;"></i> Edit Student: <?php echo htmlspecialchars($student['name'] ?? ''); ?>
            </div>

            <form method="POST">
                
                <!-- BASIC DETAILS -->
                <div class="section">
                    <h3><i class="fa-solid fa-user"></i> Basic Details</h3>
                    <div class="form-group"><label>Photo URL</label><input type="text" name="photo_url" value="<?php echo htmlspecialchars($student['photo_url'] ?? ''); ?>"></div>
                    
                    <div class="grid-2">
                        <div class="form-group"><label>Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($student['name'] ?? ''); ?>"></div>
                        <div class="form-group"><label>DOB</label><input type="date" name="dob" value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>"></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>Admission No</label><input type="text" name="admission_no" value="<?php echo htmlspecialchars($student['admission_no'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Course</label><input type="text" name="course" value="<?php echo htmlspecialchars($student['course'] ?? ''); ?>"></div>
                    </div>
                </div>

                <!-- DYNAMIC MARKS TABLES (Sem 1 to 6) -->
                <?php for ($s = 1; $s <= 6; $s++): ?>
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-graduation-cap"></i> Semester <?php echo $s; ?> Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(<?php echo $s; ?>)"><i class="fa-solid fa-plus"></i> Add Subject</button>
                    </div>
                    <div class="marks-table-wrapper">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Subject</th>
                                    <th style="width: 15%;">U.E Marks (75)</th>
                                    <th style="width: 15%;">I.A Marks (25)</th>
                                    <th style="width: 15%;">Total (100)</th>
                                    <th style="width: 15%;">Result</th>
                                    <th style="width: 8%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="sem<?php echo $s; ?>_body"></tbody>
                        </table>
                    </div>
                </div>
                <?php endfor; ?>

                <!-- BIO DATA & OTHERS -->
                <div class="section">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-address-card"></i> Additional Details</h3>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Community</label><input type="text" name="community" value="<?php echo htmlspecialchars($student['community'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Overall Assessment</label><input type="text" name="overall_assessment" value="<?php echo htmlspecialchars($student['overall_assessment'] ?? ''); ?>"></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>Days Present</label><input type="number" name="days_present" value="<?php echo htmlspecialchars($student['days_present'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Working Days</label><input type="number" name="working_days" value="<?php echo htmlspecialchars($student['working_days'] ?? ''); ?>"></div>
                    </div>

                    <div class="form-group">
                        <label>Parent Phone Number</label>
                        <input type="text" name="parent_phone" value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>" placeholder="Enter parent phone number">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="update" class="btn btn-update">
                        <i class="fa-solid fa-check"></i> Update Details
                    </button>
                    
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='student_records.php';">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </button>
                </div>

            </form>
        </div>

    </div>

</div>

<!-- JAVASCRIPT FOR DYNAMIC ROWS, AUTO LOAD & CALCULATIONS -->
<script>
function addSubjectRow(sem, subject = '', ue = '', ia = '', total = '', pf = '') {
    const tbody = document.getElementById(`sem${sem}_body`);
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td><input type="text" name="sem${sem}_subject[]" value="${subject}" placeholder="Subject Name" required></td>
        <td><input type="number" name="sem${sem}_ue[]" value="${ue}" placeholder="0" min="0" max="75" oninput="calculateMarks(this)" required></td>
        <td><input type="number" name="sem${sem}_ia[]" value="${ia}" placeholder="0" min="0" max="25" oninput="calculateMarks(this)" required></td>
        <td><input type="number" name="sem${sem}_total[]" value="${total}" readonly style="background:#F8FAFC; font-weight:700;"></td>
        <td><input type="text" name="sem${sem}_pf[]" value="${pf}" readonly class="${pf === 'Pass' ? 'pf-pass' : (pf === 'Fail' ? 'pf-fail' : '')}" style="background:#F8FAFC; text-align:center;"></td>
        <td><button type="button" class="btn-delete" title="Delete Row" onclick="removeRow(this)"><i class="fa-solid fa-trash-can"></i></button></td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    btn.closest('tr').remove();
}

function calculateMarks(input) {
    const row = input.closest('tr');
    const ueInput = row.querySelector('input[name*="_ue"]');
    const iaInput = row.querySelector('input[name*="_ia"]');
    const totalInput = row.querySelector('input[name*="_total"]');
    const pfInput = row.querySelector('input[name*="_pf"]');

    const ue = parseFloat(ueInput.value) || 0;
    const ia = parseFloat(iaInput.value) || 0;
    const total = ue + ia;

    totalInput.value = total;

    if (ueInput.value !== '' && iaInput.value !== '') {
        if (ue >= 30 && total >= 40) {
            pfInput.value = 'Pass';
            pfInput.className = 'pf-pass';
        } else {
            pfInput.value = 'Fail';
            pfInput.className = 'pf-fail';
        }
    } else {
        pfInput.value = '';
        pfInput.className = '';
    }
}

// Populate Existing Student Marks on Page Load
window.addEventListener('DOMContentLoaded', () => {
    <?php for ($s = 1; $s <= 6; $s++): 
        $raw = $student["sem{$s}_marks"] ?? '[]';
        $marks_list = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!empty($marks_list) && is_array($marks_list)):
            foreach ($marks_list as $row): 
                $sub = addslashes($row['subject'] ?? '');
                $ue  = $row['ue'] ?? 0;
                $ia  = $row['ia'] ?? 0;
                $tot = $row['total'] ?? 0;
                $pf  = addslashes($row['pass_fail'] ?? '');
            ?>
                addSubjectRow(<?php echo $s; ?>, '<?php echo $sub; ?>', '<?php echo $ue; ?>', '<?php echo $ia; ?>', '<?php echo $tot; ?>', '<?php echo $pf; ?>');
            <?php endforeach;
        else: ?>
            // Add default empty row if no data exists
            addSubjectRow(<?php echo $s; ?>);
        <?php endif;
    endfor; ?>
});
</script>

</body>
</html>