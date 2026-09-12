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
$is_admin = in_array($role, ['Admin', 'Super Admin']);

require_once __DIR__ . '/../includes/college_data.php';

// 2. Department and Class resolution
$all_departments = get_all_departments();
$session_dept = $_SESSION['department'] ?? 'CS';
$raw_dept = $is_admin ? ($_GET['dept'] ?? $session_dept) : $session_dept;
$selected_dept = normalize_dept_code($raw_dept);

$available_classes = get_department_classes($selected_dept);

$selected_class = $_GET['class'] ?? 'UG_3';
if (!isset($available_classes[$selected_class])) {
    $selected_class = array_key_first($available_classes) ?: 'UG_3';
}
$class_info = $available_classes[$selected_class];

// 3. Handle AJAX Save Request (Multi-Class Slot Updater)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');

    if (!$can_edit) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $day = $input['day'] ?? '';
    $hour = (int)($input['hour'] ?? 1);
    $subject = trim($input['subject'] ?? '');
    $span = (int)($input['span'] ?? 1);
    $target_dept = trim($input['dept'] ?? $selected_dept);
    $target_class = trim($input['class'] ?? $selected_class);

    $saved = save_class_timetable_slot($target_dept, $target_class, $day, $hour, $subject, $span);
    if ($saved) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save timetable slot.']);
    }
    exit();
}

// 4. Fetch Multi-Class Timetable Data
$timetable = get_class_timetable($selected_dept, $selected_class);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Class Timetable - Faculty Portal</title>
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
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
.badge-student { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
.badge-faculty { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }
.badge-hod { background: #DCFCE7; color: #15803D; border: 1px solid #BBF7D0; }
.badge-admin { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }

/* Page Content */
.content-body { padding: 30px 36px; }
.card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }

/* Header & Controls */
.timetable-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; padding-bottom: 18px; border-bottom: 1px solid #F1F5F9; }
.timetable-title h2 { font-size: 18px; color: #0F172A; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.timetable-title p { color: #64748B; font-size: 13px; font-weight: 500; margin-top: 4px; }

.action-btns { display: flex; gap: 10px; }
.btn { border: none; padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
.btn-print { background: #0F172A; color: white; }
.btn-print:hover { background: #1E293B; }
.btn-approve { background: linear-gradient(135deg, #10B981, #059669); color: white; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25); }
.btn-approve:hover { opacity: 0.95; transform: translateY(-1px); }

/* Timetable Grid Design */
.table-wrapper { overflow-x: auto; border: 1px solid #CBD5E1; border-radius: 12px; }
.timetable { width: 100%; border-collapse: collapse; text-align: center; background: white; }
.timetable th, .timetable td { border: 1px solid #E2E8F0; padding: 16px 12px; font-size: 13.5px; font-weight: 700; }
.timetable th { background: #F8FAFC; color: #475569; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
.timetable .day-col { background: #F1F5F9; color: #0F172A; font-weight: 800; width: 100px; font-size: 13.5px; }

/* Dynamic cell styling */
.timetable td.editable { cursor: pointer; position: relative; transition: all 0.2s; }
.timetable td.editable:hover { background: #FEF3C7; color: #B45309; }
.timetable td.lab-cell { background: #EFF6FF; color: #1D4ED8; font-weight: 800; border-color: #BFDBFE; }

/* Modal Edit Box */
.modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; }
.modal-content { background: white; padding: 26px; border-radius: 16px; width: 380px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; }
.modal-content h3 { margin-bottom: 16px; color: #0F172A; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.modal-content label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin: 12px 0 6px; }
.modal-content input, .modal-content select { width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13.5px; outline: none; font-family: inherit; }
.modal-content input:focus, .modal-content select:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px; }

/* Class & Degree Switcher Tabs */
.class-nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 22px;
}

.class-nav-tabs {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #FFFFFF;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    overflow-x: auto;
}

.class-tab-btn {
    padding: 8px 16px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 700;
    color: #64748B;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.class-tab-btn:hover {
    color: #2563EB;
    background: #EFF6FF;
}

.class-tab-btn.active {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}

.dept-select-box {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #FFFFFF;
    padding: 8px 16px;
    border-radius: 12px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}

.dept-select-box select {
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    padding: 6px 12px;
    outline: none;
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
    background: #F8FAFC;
    cursor: pointer;
    font-family: inherit;
}

@media print {
    .sidebar, .topbar, .navbar, .action-btns, .role-badge, .class-nav-container { display: none !important; }
    .main-content { margin-left: 0 !important; }
    .content-body { padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
}
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

            <li class="nav-category">College & Dept</li>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i> Circulars & Notices</a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i> Events & Calendar</a></li>

            <li class="nav-category">Faculty Panel</li>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-envelope-open-text"></i> Student Leave Requests</a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-calendar-check"></i> Apply Leave / OD</a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-pen-ruler"></i> Marks CIA</a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book-bookmark"></i> My Subjects</a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-file-pdf"></i> Syllabus & Materials</a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-tasks"></i> Assignments</a></li>
            <li><a href="timetable.php" class="active"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> Announcements</a></li>
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

<!-- MAIN CONTENT -->
<div class="main-content">
    
    <div class="topbar">
        <div class="topbar-title">
            <h1>Department Class Time Table</h1>
            <p>Schedule slots, lecture timings, and laboratory blocks</p>
        </div>
        <div class="user-profile">
            <?php 
                $badge_class = 'badge-faculty';
                if ($role === 'Student') $badge_class = 'badge-student';
                elseif ($role === 'HOD') $badge_class = 'badge-hod';
                elseif (in_array($role, ['Admin', 'Super Admin'])) $badge_class = 'badge-admin';
            ?>
            <div class="role-badge <?php echo $badge_class; ?>">
                <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($role); ?>
            </div>
        </div>
    </div>

    <div class="content-body">

        <!-- Class & Department Selector Bar -->
        <div class="class-nav-container">
            <div class="class-nav-tabs">
                <?php foreach ($available_classes as $ckey => $cdata): ?>
                    <a href="timetable.php?dept=<?=urlencode($selected_dept)?>&class=<?=urlencode($ckey)?>" 
                       class="class-tab-btn <?=$selected_class === $ckey ? 'active' : ''?>">
                        <i class="fa-solid <?=$cdata['level'] === 'PG' ? 'fa-award' : 'fa-graduation-cap'?>"></i>
                        <span><?=htmlspecialchars($cdata['short'])?> (<?=htmlspecialchars($cdata['level'])?> <?=htmlspecialchars($cdata['year'])?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($is_admin): ?>
            <div class="dept-select-box">
                <i class="fa-solid fa-building-columns" style="color:#2563EB;"></i>
                <label style="font-size:11.5px; font-weight:700; color:#64748B; text-transform:uppercase;">Department:</label>
                <select onchange="location.href='timetable.php?dept=' + encodeURIComponent(this.value) + '&class=<?=urlencode($selected_class)?>'">
                    <?php foreach ($all_departments as $d): ?>
                        <option value="<?=htmlspecialchars($d['code'])?>" <?=$selected_dept === $d['code'] ? 'selected' : ''?>>
                            <?=htmlspecialchars($d['code'])?> - <?=htmlspecialchars($d['name'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="dept-select-box">
                <i class="fa-solid fa-building-columns" style="color:#2563EB;"></i>
                <span style="font-size:12.5px; font-weight:700; color:#334155;">Dept: <b><?=htmlspecialchars($selected_dept)?></b></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            
            <div class="timetable-header">
                <div class="timetable-title">
                    <h2><i class="fa-solid fa-chalkboard-user" style="color:#2563EB;"></i> <?=htmlspecialchars(strtoupper($class_info['label']))?></h2>
                    <p>Department of <?=htmlspecialchars($selected_dept)?> &nbsp;|&nbsp; <?=htmlspecialchars($class_info['sem'])?> &nbsp;|&nbsp; Academic Year: 2026 - 2027 | Arignar Anna Government Arts College</p>
                </div>
                <div class="action-btns">
                    <button class="btn btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print / PDF</button>
                    <?php if ($is_hod): ?>
                        <button class="btn btn-approve" onclick="alert('Timetable Approved & Published Successfully!')"><i class="fa-solid fa-circle-check"></i> Approve & Publish</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TIMETABLE TABLE -->
            <div class="table-wrapper">
                <table class="timetable">
                    <thead>
                        <tr>
                            <th class="day-col">DO / HRS</th>
                            <th>Hour 1</th>
                            <th>Hour 2</th>
                            <th>Hour 3</th>
                            <th>Hour 4</th>
                            <th>Hour 5</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timetable as $day => $slots): ?>
                            <tr>
                                <td class="day-col">Day <?php echo $day; ?></td>
                                <?php foreach ($slots as $slot): ?>
                                    <td colspan="<?php echo $slot['span']; ?>" 
                                        class="<?php echo ($slot['span'] > 1) ? 'lab-cell' : ''; ?> <?php echo $can_edit ? 'editable' : ''; ?>"
                                        <?php if ($can_edit): ?> onclick="openEditModal('<?php echo $day; ?>', <?php echo $slot['hour']; ?>, '<?php echo htmlspecialchars($slot['sub']); ?>', <?php echo $slot['span']; ?>)" <?php endif; ?>>
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
        <h3><i class="fa-solid fa-pen-to-square" style="color:#2563EB;"></i> Edit Schedule Slot</h3>
        <form id="editForm" onsubmit="event.preventDefault(); saveSlot();">
            <input type="hidden" id="modal_day" name="day">
            <input type="hidden" id="modal_hour" name="hour">
            
            <label for="modal_subject">Subject & Staff Code</label>
            <input type="text" id="modal_subject" name="subject" required placeholder="e.g. DBMS(GKM)">

            <?php if ($is_hod): ?>
            <label for="modal_span">Span (Hours Count)</label>
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

const currentDept = "<?=htmlspecialchars($selected_dept, ENT_QUOTES)?>";
const currentClass = "<?=htmlspecialchars($selected_class, ENT_QUOTES)?>";

function saveSlot() {
    const day = document.getElementById('modal_day').value;
    const hour = document.getElementById('modal_hour').value;
    const subject = document.getElementById('modal_subject').value;
    const span = document.getElementById('modal_span') ? document.getElementById('modal_span').value : 1;

    fetch('timetable.php?dept=' + encodeURIComponent(currentDept) + '&class=' + encodeURIComponent(currentClass), {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ 
            day: day, 
            hour: hour, 
            subject: subject, 
            span: span,
            dept: currentDept,
            class: currentClass
        })
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