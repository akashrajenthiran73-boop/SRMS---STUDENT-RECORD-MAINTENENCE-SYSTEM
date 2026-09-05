<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session authorization (Allowing Admin & Super Admin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) { 
    header("Location: ../auth/login.php"); 
    exit(); 
} 

$role = $_SESSION['role'];

// Load .env configuration from root folder safely
$env_path = '../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// Back button action
if (isset($_POST['back'])) {
    header("Location: add_student_page1.php");
    exit;
}

// Redirect if Page 1 session data is missing
if (!isset($_SESSION['student_data'])) {
    header("Location: add_student_page1.php");
    exit;
}

// Process final form submit
if (isset($_POST['submit'])) { 
    
    // Process Dynamic Semester 4, 5, 6 Marks Data into JSON Format
    for ($sem = 4; $sem <= 6; $sem++) {
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
                        'ue'        => ($ues[$i] !== '' && $ues[$i] !== null) ? $ues[$i] : null,
                        'ia'        => ($ias[$i] !== '' && $ias[$i] !== null) ? $ias[$i] : null,
                        'total'     => ($totals[$i] !== '' && $totals[$i] !== null) ? $totals[$i] : null,
                        'pass_fail' => $pfs[$i] ?? ''
                    ];
                }
            }
        }
        $_SESSION['student_data']["sem{$sem}_marks"] = json_encode($marks_data);
    }

    // Merge Page 1 and Page 2 input data
    $student_post = $_POST;
    
    // Clean up Page 2 individual semester post arrays before merging
    for ($sem = 4; $sem <= 6; $sem++) {
        unset($student_post["sem{$sem}_subject"]);
        unset($student_post["sem{$sem}_ue"]);
        unset($student_post["sem{$sem}_ia"]);
        unset($student_post["sem{$sem}_total"]);
        unset($student_post["sem{$sem}_pf"]);
    }

    $data = array_merge($_SESSION['student_data'], $student_post); 

    // Remove action buttons
    unset($data['next']);
    unset($data['submit']);
    unset($data['back']);

    // Set fallback image if photo URL is empty
    if (empty($data['photo_url'])) {
        $data['photo_url'] = 'https://via.placeholder.com/150?text=No+Photo';
    }

    // Convert empty date fields to NULL
    if (empty($data['date_of_joining'])) $data['date_of_joining'] = null;
    if (empty($data['date_of_leaving'])) $data['date_of_leaving'] = null;
    if (empty($data['dob'])) $data['dob'] = null;

    // Convert empty numeric fields to NULL
    if (empty($data['days_present'])) $data['days_present'] = null;
    if (empty($data['working_days'])) $data['working_days'] = null;
    if (empty($data['class_rank'])) $data['class_rank'] = null;

    // Send payload to Supabase API
    $response = '';
    $http_code = 0;
    
    if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
        $url = $SUPABASE_URL . "/rest/v1/students";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY", 
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }

    if ($http_code == 201 || $http_code == 200) {
        unset($_SESSION['student_data']);
        header("Location: student_records.php?success=1");
        exit;
    } else {
        $error_display = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Student (Page 2) - SRMS</title>
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

/* Modern Sidebar */
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
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 10px 14px;
    text-decoration: none;
    font-size: 13.5px;
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

.sidebar-menu a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

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

/* Main Content Wrapper */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Navbar */
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

/* Content Body */
.content-body {
    padding: 35px 40px;
    flex: 1;
}

.form-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 34px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}

/* Stepper Progress Bar */
.stepper-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 30px;
    gap: 12px;
}
.step-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
}
.step-item.completed {
    background: #DCFCE7;
    color: #15803D;
    border: 1px solid #BBF7D0;
}
.step-item.active {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}
.step-divider {
    width: 40px;
    height: 2px;
    background: #10B981;
}

.form-header-title {
    font-size: 20px;
    color: #1E3A8A;
    font-weight: 800;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding-bottom: 14px;
    border-bottom: 1.5px solid #E2E8F0;
}

/* Modern Sections */
.section {
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 26px;
    background: #FAFBFC;
}

.section h3 {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #1E40AF;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
}

.form-group {
    margin-bottom: 16px;
}

label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #334155;
    margin-bottom: 6px;
}

input, select, textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    font-size: 13.5px;
    color: #0F172A;
    background: #FFFFFF;
    outline: none;
    transition: all 0.2s ease;
}

input:focus, select:focus, textarea:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

textarea {
    resize: vertical;
    min-height: 80px;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

/* Marks Table */
.marks-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 14px;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
}
.marks-table th, .marks-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #E2E8F0;
    border-right: 1px solid #E2E8F0;
    text-align: center;
    font-size: 13px;
}
.marks-table th:last-child, .marks-table td:last-child {
    border-right: none;
}
.marks-table tr:last-child td {
    border-bottom: none;
}
.marks-table th {
    background: #F1F5F9;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    font-size: 11.5px;
}
.marks-table input {
    width: 95%;
    padding: 7px 8px;
    border: 1.5px solid #CBD5E1;
    border-radius: 6px;
    text-align: center;
    font-size: 13px;
}

.btn-add {
    background: #059669;
    color: white;
    border: none;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    font-size: 12.5px;
    float: right;
    transition: background 0.2s;
}
.btn-add:hover { background: #047857; }

.btn-delete {
    background: #EF4444;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.2s;
}
.btn-delete:hover { background: #DC2626; }

.pf-pass { color: #059669 !important; font-weight: 700; }
.pf-fail { color: #DC2626 !important; font-weight: 700; }

/* Action Buttons */
.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 25px;
    border-top: 1px solid #E2E8F0;
    padding-top: 20px;
}

.btn {
    display: inline-flex;
    align-items: center;
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

.btn-back { background: #FFFFFF; color: #475569; border: 1.5px solid #CBD5E1; }
.btn-back:hover { background: #F8FAFC; color: #0F172A; }

.btn-submit { background: #059669; color: #FFFFFF; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25); }
.btn-submit:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35); }

.error-box {
    color: #991B1B;
    background: #FEF2F2;
    padding: 18px 22px;
    border: 1px solid #FECACA;
    margin-bottom: 25px;
    border-radius: 12px;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 16px 8px; }
    .sidebar-brand h2 span, .sidebar-brand span, .sidebar-menu a span, .menu-heading { display: none; }
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
        <h1>Add New Student</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <div class="form-card">
            
            <!-- Stepper Progress Bar -->
            <div class="stepper-bar">
                <div class="step-item completed"><i class="fa-solid fa-circle-check"></i> Step 1: Basic & Sem 1-3 Completed</div>
                <div class="step-divider"></div>
                <div class="step-item active"><i class="fa-solid fa-circle-dot"></i> Step 2: Sem 4-6 & Final Submit</div>
            </div>

            <?php if (isset($error_display) && $error_display): ?>
                <div class="error-box">
                    <h3 style="margin-bottom: 8px;"><i class="fa-solid fa-triangle-exclamation"></i> Error Saving Student Record!</h3>
                    <p><b>HTTP Code:</b> <?php echo $http_code; ?></p>
                    <p><b>Response:</b> <?php echo htmlspecialchars($response); ?></p>
                    <br>
                    <a href="add_student_page2.php" class="btn btn-back" style="padding: 8px 16px; font-size: 13px;">Try Again</a>
                </div>
            <?php endif; ?>

            <div class="form-header-title">
                <i class="fa-solid fa-user-plus" style="color: #2563EB;"></i> Student Admission Form (Step 2 of 2)
            </div>

            <form method="POST">
                
                <div class="section">
                    <h3><i class="fa-solid fa-id-card"></i> Bio Data</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>3. Date of Joining</label><input type="date" name="date_of_joining"></div>
                        <div class="form-group"><label>5. Date of Leaving</label><input type="date" name="date_of_leaving"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>4. Community (OC / BC / MBC / SC / ST)</label>
                        <select name="community">
                            <option value="">Select Community</option>
                            <option value="OC">OC</option>
                            <option value="BC">BC</option>
                            <option value="MBC">MBC</option>
                            <option value="SC">SC</option>
                            <option value="ST">ST</option>
                        </select>
                    </div>

                    <div class="form-group"><label>6. Examination Passed at the Time of Admission</label><input type="text" name="exam_at_admission"></div>
                    <div class="form-group"><label>7. College Last Studied</label><input type="text" name="last_college"></div>
                    <div class="form-group"><label>8. Part I Language</label><input type="text" name="part1_language"></div>
                </div>

                <!-- SEMESTER 4 -->
                <div class="section">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 4 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(4)">+ Add Subject</button>
                    </div>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>U.E Marks</th>
                                <th>I.A Marks</th>
                                <th>Total</th>
                                <th>Pass/Fail</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sem4_body"></tbody>
                    </table>
                </div>

                <!-- SEMESTER 5 -->
                <div class="section" style="margin-top: 20px;">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 5 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(5)">+ Add Subject</button>
                    </div>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>U.E Marks</th>
                                <th>I.A Marks</th>
                                <th>Total</th>
                                <th>Pass/Fail</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sem5_body"></tbody>
                    </table>
                </div>

                <!-- SEMESTER 6 -->
                <div class="section" style="margin-top: 20px;">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 6 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(6)">+ Add Subject</button>
                    </div>
                    <table class="marks-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>U.E Marks</th>
                                <th>I.A Marks</th>
                                <th>Total</th>
                                <th>Pass/Fail</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="sem6_body"></tbody>
                    </table>
                </div>

                 <!-- CHARACTER & ASSESSMENT -->
                <div class="section">
                    <h3><i class="fa-solid fa-award"></i> Character & Assessment</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>15. Ability for Independent Work</label><input type="text" name="independent_work"></div>
                        <div class="form-group"><label>16. Conduct</label><input type="text" name="conduct"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>17. Co-operation</label><input type="text" name="cooperation"></div>
                        <div class="form-group"><label>18. Leadership</label><input type="text" name="leadership"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>19. Overall Assessment</label>
                        <select name="overall_assessment">
                            <option value="">Select Assessment</option>
                            <option value="Outstanding">Outstanding</option>
                            <option value="Very Good">Very Good</option>
                            <option value="Good">Good</option>
                            <option value="Satisfactory">Satisfactory</option>
                            <option value="Ordinary">Ordinary</option>
                        </select>
                    </div>
                    
                    <div class="form-group"><label>20. Head of the Department of</label><input type="text" name="hod_name"></div>
                </div>

                <div class="actions">
                    <button type="submit" name="back" class="btn btn-back"><i class="fa-solid fa-arrow-left"></i> Back</button>
                    <button type="submit" name="submit" class="btn btn-submit"><i class="fa-solid fa-check"></i> Submit Student Details</button>
                </div>

            </form>
        </div>

    </div>

</div>

<!-- JAVASCRIPT FOR DYNAMIC ROWS & AUTO CALCULATIONS -->
<script>
function addSubjectRow(sem, subject = '', ue = '', ia = '', total = '', pf = '') {
    const tbody = document.getElementById(`sem${sem}_body`);
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td><input type="text" name="sem${sem}_subject[]" value="${subject}" placeholder="Subject Name"></td>
        <td><input type="number" name="sem${sem}_ue[]" value="${ue}" placeholder="0" min="0" max="75" oninput="calculateMarks(this)"></td>
        <td><input type="number" name="sem${sem}_ia[]" value="${ia}" placeholder="0" min="0" max="25" oninput="calculateMarks(this)"></td>
        <td><input type="number" name="sem${sem}_total[]" value="${total}" readonly style="background:#F8FAFC;"></td>
        <td><input type="text" name="sem${sem}_pf[]" value="${pf}" readonly class="${pf === 'Pass' ? 'pf-pass' : (pf === 'Fail' ? 'pf-fail' : '')}" style="background:#F8FAFC;"></td>
        <td><button type="button" class="btn-delete" onclick="removeRow(this)">🗑️</button></td>
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

    const rawUE = ueInput.value.trim();
    const rawIA = iaInput.value.trim();

    // If both fields are empty, keep outputs empty
    if (rawUE === '' && rawIA === '') {
        totalInput.value = '';
        pfInput.value = '';
        pfInput.className = '';
        return;
    }

    const ue = parseFloat(rawUE) || 0;
    const ia = parseFloat(rawIA) || 0;
    const total = ue + ia;

    totalInput.value = total;

    if (rawUE !== '' || rawIA !== '') {
        if (ue >= 30 && total >= 40) {
            pfInput.value = 'Pass';
            pfInput.className = 'pf-pass';
        } else {
            pfInput.value = 'Fail';
            pfInput.className = 'pf-fail';
        }
    }
}

// Load default 3 rows per semester on page load
window.addEventListener('DOMContentLoaded', () => {
    [4, 5, 6].forEach(sem => {
        addSubjectRow(sem, 'Subject 1');
        addSubjectRow(sem, 'Subject 2');
        addSubjectRow(sem, 'Subject 3');
    });
});
</script>

</body>
</html>