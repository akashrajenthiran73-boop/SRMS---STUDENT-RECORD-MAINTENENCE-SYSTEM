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
$events = get_college_events();

// Filter for students: Show events for 'ALL' or 'CS'
$student_events = array_filter($events, function($e) {
    $target = $e['dept_code'] ?? 'ALL';
    return ($target === 'ALL' || $target === 'CS');
});

// Filtering
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

$filtered_events = array_filter($student_events, function($e) use ($type_filter, $search) {
    if ($type_filter !== '' && ($e['event_type'] ?? '') !== $type_filter) {
        return false;
    }
    if ($search !== '') {
        $text = ($e['title'] ?? '') . ' ' . ($e['venue'] ?? '') . ' ' . ($e['description'] ?? '');
        if (stripos($text, $search) === false) {
            return false;
        }
    }
    return true;
});

// Sort by date ascending
usort($filtered_events, function($a, $b) {
    return strcmp($a['event_date'] ?? '', $b['event_date'] ?? '');
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Events & Calendar | Student Portal</title>
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

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            border-radius: 20px; padding: 28px 32px; color: #FFFFFF; margin-bottom: 25px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .hero-banner h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .hero-banner p { font-size: 13.5px; color: #94A3B8; }

        /* Toolbar */
        .toolbar {
            background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px;
            padding: 14px 18px; margin-bottom: 24px; display: flex; gap: 12px;
            flex-wrap: wrap; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .search-box { position: relative; flex: 1; min-width: 220px; }
        .search-box input {
            width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13.5px; outline: none;
        }
        .search-box input:focus { border-color: #059669; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .select-filter {
            padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px;
            font-size: 13px; font-weight: 600; color: #334155; background: white; outline: none;
        }
        .btn-filter {
            padding: 10px 18px; background: #059669; color: white; border: none;
            border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-reset {
            padding: 10px 16px; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
        }

        /* Event Cards */
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 20px; }
        .event-card {
            background: white; border: 1px solid #E2E8F0; border-radius: 16px;
            overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); border-color: #A7F3D0; }
        .event-card-body { padding: 22px; }
        .date-badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px;
            background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0;
            border-radius: 20px; font-size: 12px; font-weight: 700; margin-bottom: 12px;
        }
        .type-tag {
            float: right; font-size: 11px; font-weight: 700; text-transform: uppercase;
            padding: 4px 10px; border-radius: 6px; background: #F1F5F9; color: #475569;
        }
        .event-title { font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 8px; }
        .event-desc { font-size: 13px; color: #64748B; line-height: 1.6; margin-bottom: 16px; }
        .event-card-footer {
            padding: 14px 22px; background: #F8FAFC; border-top: 1px solid #F1F5F9;
            display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748B;
        }

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
            <li><a href="events.php" class="active"><i class="fa-solid fa-calendar-check"></i> <span>Events & Calendar</span></a></li>
            <li><a href="grievance.php"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

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
            <h1>Academic Events & Calendar</h1>
            <p>Upcoming college fests, examinations, workshops, and sports meets</p>
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
                <h1>College Activities & Key Dates</h1>
                <p>Never miss important campus events, semester exams, symposium deadlines, and sports meets.</p>
            </div>
            <div style="background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 30px; font-size: 12.5px; font-weight: 700; border: 1px solid rgba(255,255,255,0.25);">
                <i class="fa-regular fa-calendar-days"></i> <?=count($filtered_events)?> Scheduled
            </div>
        </div>

        <!-- Toolbar -->
        <form method="GET" class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search events by title, venue or keywords...">
            </div>

            <select name="type" class="select-filter">
                <option value="">All Event Types</option>
                <option value="Academic" <?=$type_filter === 'Academic' ? 'selected' : ''?>>Academic</option>
                <option value="Exam" <?=$type_filter === 'Exam' ? 'selected' : ''?>>Examinations</option>
                <option value="Cultural" <?=$type_filter === 'Cultural' ? 'selected' : ''?>>Cultural</option>
                <option value="Workshop" <?=$type_filter === 'Workshop' ? 'selected' : ''?>>Workshop / Symposium</option>
                <option value="Sports" <?=$type_filter === 'Sports' ? 'selected' : ''?>>Sports</option>
            </select>

            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($type_filter || $search): ?>
                <a href="events.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            <?php endif; ?>
        </form>

        <!-- Events Cards -->
        <?php if (empty($filtered_events)): ?>
            <div style="background: white; border: 1px dashed #CBD5E1; border-radius: 16px; padding: 48px 20px; text-align: center;">
                <i class="fa-regular fa-calendar-xmark" style="font-size: 38px; color: #94A3B8; margin-bottom: 12px;"></i>
                <h3 style="font-size: 16px; font-weight: 700; color: #1E293B;">No Events Found</h3>
                <p style="font-size: 13px; color: #64748B; margin-top: 4px;">Check back soon for new events or clear your filters.</p>
            </div>
        <?php else: ?>
            <div class="event-grid">
                <?php foreach ($filtered_events as $ev): ?>
                    <div class="event-card">
                        <div class="event-card-body">
                            <div>
                                <span class="date-badge">
                                    <i class="fa-regular fa-calendar"></i> <?=htmlspecialchars($ev['event_date'] ?? '')?>
                                </span>
                                <span class="type-tag"><?=htmlspecialchars($ev['event_type'] ?? 'Event')?></span>
                            </div>
                            <h3 class="event-title"><?=htmlspecialchars($ev['title'] ?? '')?></h3>
                            <p class="event-desc"><?=htmlspecialchars($ev['description'] ?? '')?></p>
                        </div>
                        <div class="event-card-footer">
                            <span><i class="fa-solid fa-location-dot" style="margin-right: 4px; color:#DC2626;"></i> <?=htmlspecialchars($ev['venue'] ?? 'Campus')?></span>
                            <span style="font-weight: 600; color: #059669;">
                                <i class="fa-solid fa-building-columns"></i> <?=htmlspecialchars($ev['dept_code'] ?? 'ALL')?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>
