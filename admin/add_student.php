<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session authorization
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) { 
    header("Location: ../auth/login.php"); 
    exit(); 
} 

$role = $_SESSION['role'];

if (isset($_POST['next'])) {
    
    // 1. Process Dynamic Semester Marks Data into JSON Format
    for ($sem = 1; $sem <= 3; $sem++) {
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
        
        // Save as JSON string or Array for Session
        $_POST["sem{$sem}_marks"] = json_encode($marks_data);

        // Remove raw form array inputs so they don't get sent as missing columns to Supabase
        unset($_POST["sem{$sem}_subject"]);
        unset($_POST["sem{$sem}_ue"]);
        unset($_POST["sem{$sem}_ia"]);
        unset($_POST["sem{$sem}_total"]);
        unset($_POST["sem{$sem}_pf"]);
    }

    // Save Page 1 cleaned data to session
    $_SESSION['student_data'] = $_POST; 

    header("Location: add_student_page2.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add New Student (Page 1) - SRMS</title>
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
.step-item.active {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}
.step-item.inactive {
    background: #F8FAFC;
    color: #94A3B8;
    border: 1px solid #E2E8F0;
}
.step-divider {
    width: 40px;
    height: 2px;
    background: #E2E8F0;
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
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 22px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: all 0.25s ease;
}

.btn-primary { background: #2563EB; color: #FFFFFF; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-primary:hover { background: #1D4ED8; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }

.btn-light { background: #FFFFFF; color: #475569; border: 1.5px solid #CBD5E1; }
.btn-light:hover { background: #F8FAFC; color: #0F172A; }

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
                <div class="step-item active"><i class="fa-solid fa-circle-check"></i> Step 1: Basic Details & Sem 1-3</div>
                <div class="step-divider"></div>
                <div class="step-item inactive"><i class="fa-regular fa-circle"></i> Step 2: Sem 4-6 & Submit</div>
            </div>

            <div class="form-header-title">
                <i class="fa-solid fa-user-plus" style="color: #2563EB;"></i> Student Admission Form (Step 1 of 2)
            </div>

            <form method="POST">
                
                <div class="section">
                    <h3><i class="fa-solid fa-address-card"></i> Basic Details</h3>
                    
                    <div class="form-group">
                        <label>Student Photo (URL)</label>
                        <input type="text" name="photo_url" placeholder="https://example.com/photo.jpg">
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>1. Name</label><input type="text" name="name" required></div>
                        <div class="form-group"><label>2. Date of Birth</label><input type="date" name="dob" required></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>3. Admission Number</label><input type="text" name="admission_no" required></div>
                        <div class="form-group"><label>4. Roll Number</label><input type="text" name="roll_no"></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>5. Parent's Name</label><input type="text" name="parent_name"></div>
                        <div class="form-group"><label>Parent's Occupation</label><input type="text" name="parent_occupation"></div>
                    </div>

                    <div class="form-group"><label>Parent's Address</label><textarea name="parent_address"></textarea></div>
                    <div class="form-group"><label>6. Permanent Address</label><textarea name="permanent_address"></textarea></div>

                    <div class="grid-2">
                        <div class="form-group"><label>7. Course</label><input type="text" name="course"></div>
                        <div class="form-group"><label>8. Main Subject</label><input type="text" name="main_subject"></div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>9. Medium</label><input type="text" name="medium"></div>
                        <div class="form-group"><label>10. Ancillary Subjects</label><input type="text" name="ancillary_subjects"></div>
                    </div>

                    <div class="form-group"><label>11. Examination Register Number</label><input type="text" name="exam_reg_no"></div>
                </div>

                <!-- SEMESTER 1 -->
                <div class="section">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 1 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(1)">+ Add Subject</button>
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
                        <tbody id="sem1_body"></tbody>
                    </table>
                </div>

                <!-- SEMESTER 2 -->
                <div class="section" style="margin-top: 20px;">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 2 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(2)">+ Add Subject</button>
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
                        <tbody id="sem2_body"></tbody>
                    </table>
                </div>

                <!-- SEMESTER 3 -->
                <div class="section" style="margin-top: 20px;">
                    <div style="overflow: hidden; margin-bottom: 10px;">
                        <h3 style="float: left; margin: 0;"><i class="fa-solid fa-graduation-cap"></i> Semester 3 Marks</h3>
                        <button type="button" class="btn-add" onclick="addSubjectRow(3)">+ Add Subject</button>
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
                        <tbody id="sem3_body"></tbody>
                    </table>
                </div>

                <!-- ATTENDANCE & RESULTS -->
                <div class="section">
                    <h3><i class="fa-solid fa-chart-line"></i> Attendance & Results</h3>
                    <div class="grid-2">
                        <div class="form-group"><label>Days Present</label><input type="number" name="days_present"></div>
                        <div class="form-group"><label>Working Days</label><input type="number" name="working_days"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Community Service</label><input type="text" name="community_service"></div>
                        <div class="form-group"><label>Special Activities</label><input type="text" name="special_activities"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Final Exam Result</label><input type="text" name="final_exam_result"></div>
                        <div class="form-group"><label>Rank Obtained</label><input type="number" name="class_rank"></div>
                    </div>
                    <div class="form-group"><label>Scholarships & Concessions</label><textarea name="scholarships"></textarea></div>
                    <div class="form-group"><label>College Prizes Awarded</label><textarea name="prizes"></textarea></div>
                </div>

                <div class="form-actions">
                    <a href="student_records.php" class="btn btn-light"><i class="fa-solid fa-arrow-left"></i> Cancel</a>
                    <button type="submit" name="next" class="btn btn-primary">Next → Page 2</button>
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
    [1, 2, 3].forEach(sem => {
        addSubjectRow(sem, 'Tamil');
        addSubjectRow(sem, 'English');
        addSubjectRow(sem, 'Maths');
    });
});
</script>

</body>
</html>