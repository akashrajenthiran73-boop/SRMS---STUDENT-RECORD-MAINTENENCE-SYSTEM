<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/college_data.php';

$student_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Student';
$student_id = strval($_SESSION['user_id'] ?? 'STU001');
$reg_no = $_SESSION['reg_no'] ?? 'REG-N/A';
$dept_code = 'CS';

$msg = '';
$msg_type = '';

// Handle grievance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_grievance'])) {
    $category = trim($_POST['category'] ?? 'Academic');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($subject) || empty($description)) {
        $msg = 'Please fill in both the subject and description.';
        $msg_type = 'error';
    } else {
        $ticket = submit_student_grievance([
            'student_id' => $student_id,
            'student_name' => $student_name,
            'reg_no' => $reg_no,
            'department' => $dept_code,
            'category' => $category,
            'subject' => $subject,
            'description' => $description
        ]);
        $msg = "Your grievance has been registered successfully! Ticket Reference: #{$ticket}";
        $msg_type = 'success';
    }
}

// Fetch grievances for this student
$my_grievances = get_student_grievances('All', $student_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Grievance & Helpdesk | Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

        /* Unified Student Sidebar */
        .sidebar {
            width: 260px; height: 100vh; position: fixed; left: 0; top: 0;
            background: #0B132B; color: white; display: flex; flex-direction: column;
            justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05);
        }
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

        /* Main Content */
        .main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
        .topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
        .topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
        .topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
        .user-profile { display: flex; align-items: center; gap: 12px; }
        .role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; letter-spacing: 0.3px; }
        .content-body { padding: 30px 36px; }

        /* Banner */
        .hero-banner {
            background: linear-gradient(135deg, #1E1B4B 0%, #312E81 100%);
            border-radius: 20px; padding: 28px 32px; color: #FFFFFF; margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(30, 27, 75, 0.12); display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .hero-banner h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .hero-banner p { font-size: 13.5px; color: #C7D2FE; }

        /* Alert Box */
        .alert {
            padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 13.5px; font-weight: 600;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }

        /* Two Columns Layout */
        .layout-grid {
            display: grid; grid-template-columns: 1.1fr 1.4fr; gap: 28px;
        }
        @media(max-width: 1100px) {
            .layout-grid { grid-template-columns: 1fr; }
        }

        /* Form Card */
        .card {
            background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 18px;
            padding: 26px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .card-header {
            font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 18px;
            display: flex; align-items: center; gap: 10px; padding-bottom: 14px; border-bottom: 1px solid #F1F5F9;
        }
        .card-header i { color: #059669; }

        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 11px 14px; border: 1px solid #CBD5E1; border-radius: 10px;
            font-size: 13.5px; outline: none; transition: 0.2s; background: white;
        }
        .form-control:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.1); }
        textarea.form-control { resize: vertical; min-height: 110px; }

        .btn-submit {
            width: 100%; padding: 12px; background: linear-gradient(135deg, #059669, #047857);
            color: white; border: none; border-radius: 10px; font-size: 14px; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; box-shadow: 0 4px 12px rgba(5,150,105,0.25); transition: 0.2s;
        }
        .btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }

        /* Ticket Cards */
        .ticket-list { display: flex; flex-direction: column; gap: 16px; }
        .ticket-item {
            background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 14px;
            padding: 18px; transition: 0.2s;
        }
        .ticket-item:hover { border-color: #CBD5E1; background: white; box-shadow: 0 4px 14px rgba(0,0,0,0.04); }
        .ticket-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px; }
        .ticket-no { font-size: 13px; font-weight: 800; color: #0F172A; }
        .status-badge {
            font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px;
            border-radius: 20px; letter-spacing: 0.4px;
        }
        .status-pending { background: #FEF3C7; color: #B45309; border: 1px solid #FDE68A; }
        .status-in-review { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
        .status-resolved { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }

        .ticket-title { font-size: 14.5px; font-weight: 700; color: #1E293B; margin-bottom: 6px; }
        .ticket-desc { font-size: 13px; color: #64748B; line-height: 1.5; margin-bottom: 12px; }
        
        .admin-reply-box {
            background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 10px;
            padding: 12px 14px; margin-top: 10px; font-size: 13px; color: #1E3A8A;
        }
        .admin-reply-title { font-weight: 700; font-size: 12px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }

        @media(max-width: 900px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .topbar, .content-body { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
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
            <li><a href="grievance.php" class="active"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
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

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <div class="topbar-title">
            <h1>Student Grievance & Query Cell</h1>
            <p>Direct official redressal channel to Department HOD & College Administration</p>
        </div>
        <div class="user-profile">
            <span style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-building-columns"></i> B.Sc CS Dept
            </span>
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($student_name)?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="hero-banner">
            <div>
                <h1>College Grievance Redressal Portal</h1>
                <p>Submit inquiries regarding academic marks, exam revaluation, attendance correction, certificates or facilities.</p>
            </div>
            <div style="background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 30px; font-size: 12.5px; font-weight: 700; border: 1px solid rgba(255,255,255,0.25);">
                <i class="fa-solid fa-ticket"></i> <?=count($my_grievances)?> Tickets Registered
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?=$msg_type?>">
                <i class="fa-solid <?=$msg_type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'?>"></i>
                <?=$msg?>
            </div>
        <?php endif; ?>

        <div class="layout-grid">
            
            <!-- Submit Ticket Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-pen-to-square"></i> Register New Grievance
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="Academic">Academic / CIA Marks</option>
                            <option value="Examination">Examination & Hall Ticket</option>
                            <option value="Fee & Scholarship">Scholarship / Fee Concession</option>
                            <option value="Certificates">Bonafide & Transfer Certificate</option>
                            <option value="Facilities">Classroom & Lab Facilities</option>
                            <option value="General">Other Grievance</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Subject / Short Summary *</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g., CIA II Mark clarification in Data Structures" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Detailed Description *</label>
                        <textarea name="description" class="form-control" placeholder="Describe your concern in detail so administration can review quickly..." required></textarea>
                    </div>

                    <button type="submit" name="submit_grievance" class="btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit Grievance
                    </button>
                </form>
            </div>

            <!-- History of Tickets -->
            <div class="card">
                <div class="card-header">
                    <i class="fa-solid fa-clock-rotate-left"></i> My Submitted Grievance History
                </div>
                
                <?php if (empty($my_grievances)): ?>
                    <div style="text-align: center; padding: 40px 10px; color: #94A3B8;">
                        <i class="fa-solid fa-clipboard-check" style="font-size: 36px; margin-bottom: 12px; color:#CBD5E1;"></i>
                        <h4 style="font-size: 15px; color:#1E293B; font-weight: 700;">No Grievances Submitted</h4>
                        <p style="font-size: 13px; color:#64748B; margin-top: 4px;">You haven't filed any tickets yet. Use the form on the left if you have queries.</p>
                    </div>
                <?php else: ?>
                    <div class="ticket-list">
                        <?php foreach ($my_grievances as $g): 
                            $status = $g['status'] ?? 'Pending';
                            $status_class = match($status) {
                                'Resolved' => 'status-resolved',
                                'In Review' => 'status-in-review',
                                default => 'status-pending'
                            };
                        ?>
                            <div class="ticket-item">
                                <div class="ticket-top">
                                    <span class="ticket-no"><i class="fa-solid fa-hashtag"></i> <?=htmlspecialchars($g['ticket_no'] ?? '')?></span>
                                    <span class="status-badge <?=$status_class?>"><?=htmlspecialchars($status)?></span>
                                </div>
                                <h4 class="ticket-title"><?=htmlspecialchars($g['subject'] ?? '')?></h4>
                                <p class="ticket-desc"><?=htmlspecialchars($g['description'] ?? '')?></p>
                                
                                <div style="font-size: 11.5px; color: #94A3B8; display: flex; justify-content: space-between;">
                                    <span><i class="fa-regular fa-calendar"></i> <?=htmlspecialchars($g['submitted_date'] ?? '')?></span>
                                    <span><i class="fa-solid fa-tag"></i> <?=htmlspecialchars($g['category'] ?? 'General')?></span>
                                </div>

                                <?php if (!empty($g['admin_reply'])): ?>
                                    <div class="admin-reply-box">
                                        <div class="admin-reply-title">
                                            <i class="fa-solid fa-reply"></i> Official Response from Administration:
                                        </div>
                                        <div><?=htmlspecialchars($g['admin_reply'])?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

</body>
</html>
