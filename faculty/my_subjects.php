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
    echo "<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied: Faculty Access Only</h2>";
    exit();
}

$faculty_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';

// 2. Supabase Configuration
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = parse_ini_file($env_path);
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}

// 3. API Actions (Add / Edit / Delete Subject via Ajax POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    $input  = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'save') {
        $subject_id   = $input['id'] ?? null;
        $subject_name = trim($input['subject_name'] ?? '');
        $subject_code = trim($input['subject_code'] ?? '');
        $class_assigned = trim($input['class_assigned'] ?? 'Not Assigned');

        if (empty($subject_name) || empty($subject_code)) {
            echo json_encode(['success' => false, 'message' => 'Subject Name & Code are required!']);
            exit();
        }

        $payload = [
            'faculty_email' => $faculty_email,
            'subject_name'  => $subject_name,
            'subject_code'  => $subject_code,
            'class_assigned'=> $class_assigned
        ];

        // Edit existing or Add new
        if (!empty($subject_id)) {
            $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects?id=eq." . urlencode($subject_id);
            $method = "PATCH";
        } else {
            $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects";
            $method = "POST";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 200 || $code == 201 || $code == 204) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $res]);
        }
        exit();
    }

    if ($action === 'delete') {
        $subject_id = $input['id'] ?? '';
        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects?id=eq." . urlencode($subject_id);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY"
        ]);

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 200 || $code == 204) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $res]);
        }
        exit();
    }
}

// 4. Fetch Subjects for Current Faculty Email
$my_subjects = [];
if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY) && !empty($faculty_email)) {
    $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects?faculty_email=eq." . urlencode($faculty_email) . "&order=id.desc";

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

    $my_subjects = json_decode($res, true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Subjects - Faculty Portal</title>
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
.sidebar-brand .logo-icon { width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #2563EB, #1D4ED8); display: flex; align-items: center; justify-content: center; font-size: 18px; color: #FFFFFF; }
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

/* Content Layout */
.content-body { padding: 30px 36px; display: grid; grid-template-columns: 1fr 1.6fr; gap: 26px; }
@media (max-width: 1024px) {
    .content-body { grid-template-columns: 1fr; }
}

.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #F1F5F9; }
.card-title { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: #0F172A; }
.card-title i { color: #2563EB; }

/* Form Elements */
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin-bottom: 6px; }
.form-group input { width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 10px; font-size: 13.5px; outline: none; transition: border-color 0.2s; color: #0F172A; background: #FFFFFF; }
.form-group input:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }

.btn-submit { width: 100%; background: linear-gradient(135deg, #2563EB, #1D4ED8); color: white; border: none; padding: 12px; border-radius: 10px; font-size: 13.5px; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }
.btn-cancel { background: #64748B; margin-top: 8px; display: none; box-shadow: none; }
.btn-cancel:hover { background: #475569; }

/* Table */
.table-wrapper { overflow-x: auto; border: 1px solid #E2E8F0; border-radius: 12px; }
.sub-table { width: 100%; border-collapse: collapse; text-align: left; }
.sub-table th { background: #F8FAFC; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.6px; padding: 12px 14px; border-bottom: 1px solid #E2E8F0; }
.sub-table td { padding: 13px 14px; font-size: 13.5px; border-bottom: 1px solid #F1F5F9; color: #334155; }
.sub-table tr:last-child td { border-bottom: none; }
.sub-table tr:hover td { background-color: #F8FAFC; }

.badge-code { background: #EEF2FF; color: #4338CA; font-weight: 700; padding: 4px 8px; border-radius: 6px; font-size: 11px; border: 1px solid #E0E7FF; }
.badge-class { background: #FEF3C7; color: #B45309; font-weight: 700; padding: 4px 8px; border-radius: 6px; font-size: 11px; border: 1px solid #FDE68A; }

.btn-act { border: none; padding: 6px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
.btn-edit { background: #F59E0B; color: white; margin-right: 4px; }
.btn-edit:hover { background: #D97706; }
.btn-del { background: #EF4444; color: white; }
.btn-del:hover { background: #DC2626; }
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
            <li><a href="leave_faculty.php"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php" class="active"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
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
            <h1>My Allocated Subjects</h1>
            <p>Manage courses, subject codes, and class allocations</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-tie"></i> Faculty Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($faculty_email); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <!-- Add / Edit Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <span id="formTitle"><i class="fa-solid fa-circle-plus"></i> Add New Subject</span>
                </div>
            </div>
            <form id="subjectForm" onsubmit="saveSubject(event)">
                <input type="hidden" id="subject_id">

                <div class="form-group">
                    <label for="subject_name">Subject Name</label>
                    <input type="text" id="subject_name" placeholder="e.g. Operating System, DBMS" required>
                </div>

                <div class="form-group">
                    <label for="subject_code">Subject Code</label>
                    <input type="text" id="subject_code" placeholder="e.g. CS3401, CS3402" required>
                </div>

                <div class="form-group">
                    <label for="class_assigned">Assigned Class / Department</label>
                    <input type="text" id="class_assigned" placeholder="e.g. CSE-A, IT-B, III Year" required>
                </div>

                <button type="submit" id="submitBtn" class="btn-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save Subject
                </button>
                <button type="button" id="cancelBtn" class="btn-submit btn-cancel" onclick="resetForm()">
                    Cancel Edit
                </button>
            </form>
        </div>

        <!-- Subjects List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Assigned Subjects List</span>
                </div>
                <span style="font-size: 12px; color: #64748B; font-weight: 500;">
                    Total: <?php echo count($my_subjects); ?> subjects
                </span>
            </div>
            
            <div class="table-wrapper">
                <table class="sub-table">
                    <thead>
                        <tr>
                            <th>Subject Name</th>
                            <th>Code</th>
                            <th>Class</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_subjects)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94A3B8; padding: 32px 16px;">
                                    <i class="fa-solid fa-book-open" style="font-size: 26px; display: block; margin-bottom: 8px; color: #CBD5E1;"></i>
                                    No subjects added yet. Add your first subject using the form.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($my_subjects as $sub): 
                                $s_id    = $sub['id'];
                                $s_name  = $sub['subject_name'] ?? 'N/A';
                                $s_code  = $sub['subject_code'] ?? 'N/A';
                                $s_class = $sub['class_assigned'] ?? 'Not Assigned';
                            ?>
                            <tr>
                                <td><strong style="color: #0F172A;"><?php echo htmlspecialchars($s_name); ?></strong></td>
                                <td><span class="badge-code"><?php echo htmlspecialchars($s_code); ?></span></td>
                                <td><span class="badge-class"><?php echo htmlspecialchars($s_class); ?></span></td>
                                <td>
                                    <button class="btn-act btn-edit" onclick='editSubject(<?php echo json_encode($sub); ?>)'>
                                        <i class="fa-solid fa-pen"></i> Edit
                                    </button>
                                    <button class="btn-act btn-del" onclick="deleteSubject(<?php echo $s_id; ?>)">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
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
function saveSubject(e) {
    e.preventDefault();

    const id            = document.getElementById('subject_id').value;
    const subjectName   = document.getElementById('subject_name').value;
    const subjectCode   = document.getElementById('subject_code').value;
    const classAssigned = document.getElementById('class_assigned').value;

    fetch('my_subjects.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'save',
            id: id,
            subject_name: subjectName,
            subject_code: subjectCode,
            class_assigned: classAssigned
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Subject saved successfully!');
            window.location.reload();
        } else {
            alert('Error saving subject: ' + (data.message || JSON.stringify(data.error)));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server Connection Error!');
    });
}

function editSubject(sub) {
    document.getElementById('subject_id').value = sub.id;
    document.getElementById('subject_name').value = sub.subject_name;
    document.getElementById('subject_code').value = sub.subject_code;
    document.getElementById('class_assigned').value = sub.class_assigned || '';

    document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Edit Subject';
    document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> Update Subject';
    document.getElementById('cancelBtn').style.display = 'block';
}

function resetForm() {
    document.getElementById('subject_id').value = '';
    document.getElementById('subjectForm').reset();
    document.getElementById('formTitle').innerHTML = '<i class="fa-solid fa-circle-plus"></i> Add New Subject';
    document.getElementById('submitBtn').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Subject';
    document.getElementById('cancelBtn').style.display = 'none';
}

function deleteSubject(id) {
    if (!confirm('Are you sure you want to delete this subject?')) return;

    fetch('my_subjects.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'delete',
            id: id
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('Subject deleted!');
            window.location.reload();
        } else {
            alert('Error deleting subject!');
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