<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../includes/college_data.php';

$faculty_name = $_SESSION['username'] ?? 'Faculty Member';
$events = get_college_events();
$departments = get_college_departments();

// Filtering
$type_filter = $_GET['type'] ?? '';
$search = trim($_GET['search'] ?? '');

$filtered_events = array_filter($events, function($e) use ($type_filter, $search) {
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
    <title>Academic Events & Calendar | Faculty Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #F8FAFC; color: #1E293B; min-height: 100vh; display: flex; }

        /* Sidebar Styles */
        .sidebar {
            width: 260px; height: 100vh; position: fixed; left: 0; top: 0;
            background: #0B132B; color: white; padding: 24px 16px;
            display: flex; flex-direction: column; justify-content: space-between;
            box-shadow: 4px 0 25px rgba(0, 0, 0, 0.05); z-index: 100;
        }
        .sidebar-brand {
            text-align: center; padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08); flex-shrink: 0;
        }
        .sidebar-brand h2 {
            color: #F59E0B; font-size: 20px; font-weight: 800; letter-spacing: 0.5px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .sidebar-brand span {
            font-size: 11px; color: #64748B; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;
        }
        .sidebar-nav-container { flex-grow: 1; overflow-y: auto; margin-top: 15px; padding-right: 4px; }
        .sidebar-nav-container::-webkit-scrollbar { width: 4px; }
        .sidebar-nav-container::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }
        .sidebar-menu { list-style: none; }
        .sidebar-menu li { margin-bottom: 4px; }
        .menu-category {
            font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase;
            letter-spacing: 1.2px; padding: 14px 14px 6px 14px;
        }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 12px; padding: 11px 16px;
            color: #94A3B8; text-decoration: none; font-weight: 500; font-size: 13.5px;
            border-radius: 10px; transition: all 0.25s ease;
        }
        .sidebar-menu a:hover { color: #F8FAFC; background: rgba(255, 255, 255, 0.06); }
        .sidebar-menu a.active {
            background: #2563EB; color: #FFFFFF; font-weight: 600;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .sidebar-menu a i { font-size: 16px; width: 20px; text-align: center; }
        .sidebar-footer { flex-shrink: 0; border-top: 1px solid rgba(255, 255, 255, 0.08); padding-top: 12px; }

        /* Main Content */
        .main-content { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: #FFFFFF; padding: 18px 40px; display: flex; justify-content: space-between;
            align-items: center; border-bottom: 1px solid #E2E8F0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02); position: sticky; top: 0; z-index: 50;
        }
        .topbar h2 {
            color: #1E3A8A; font-size: 22px; font-weight: 800; letter-spacing: -0.3px;
            display: flex; align-items: center; gap: 10px;
        }
        .topbar-right { display: flex; align-items: center; gap: 12px; }
        .role-badge {
            background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE;
            padding: 7px 16px; border-radius: 30px; font-size: 13px; font-weight: 700;
            display: flex; align-items: center; gap: 8px;
        }
        .content-body { padding: 30px 40px; }

        /* Banner */
        .banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E3A8A 100%);
            border-radius: 18px; padding: 26px 32px; color: white; margin-bottom: 25px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15); display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .banner h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .banner p { font-size: 13.5px; opacity: 0.9; }

        /* Toolbar */
        .toolbar {
            background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px;
            padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 12px;
            flex-wrap: wrap; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .search-box { position: relative; flex: 1; min-width: 220px; }
        .search-box input {
            width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13.5px; outline: none;
        }
        .search-box input:focus { border-color: #2563EB; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .select-filter {
            padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px;
            font-size: 13px; font-weight: 600; color: #334155; background: white; outline: none;
        }
        .btn-filter {
            padding: 10px 18px; background: #2563EB; color: white; border: none;
            border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-reset {
            padding: 10px 16px; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
        }

        /* Event Grid */
        .event-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .event-card {
            background: white; border: 1px solid #E2E8F0; border-radius: 16px;
            overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .event-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px rgba(0,0,0,0.07); border-color: #BFDBFE; }
        .event-card-body { padding: 22px; }
        .date-badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px;
            background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE;
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
            .sidebar { width: 70px; padding: 20px 8px; }
            .sidebar-brand span, .sidebar-menu span, .menu-category { display: none; }
            .sidebar-brand h2 { font-size: 18px; }
            .sidebar-menu a { justify-content: center; padding: 12px; }
            .main-content { margin-left: 70px; }
            .topbar { padding: 16px 20px; }
            .content-body { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- Sidebar Menu -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Faculty Portal</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_faculty.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
            <li><a href="student_record.php"><i class="fa-solid fa-users"></i><span>Students Record</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i><span>Bio Data</span></a></li>
            <li><a href="umis_data.php"><i class="fa-solid fa-clipboard-user"></i><span>UMIS Data</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i><span>Result Analysis</span></a></li>

            <div class="menu-category">College & Dept</div>
            <li><a href="circulars.php"><i class="fa-solid fa-bullhorn"></i><span>Circulars & Notices</span></a></li>
            <li><a href="events.php" class="active"><i class="fa-solid fa-calendar-check"></i><span>Events & Calendar</span></a></li>

            <div class="menu-category">Faculty Panel</div>
            <li><a href="student_leave_requests.php"><i class="fa-solid fa-user-check"></i><span>Student Leave Requests</span></a></li>
            <li><a href="leave_faculty.php"><i class="fa-solid fa-file-pen"></i><span>Apply Leave</span></a></li>
            <li><a href="marks_cia.php"><i class="fa-solid fa-chart-line"></i><span>Marks CIA</span></a></li>
            <li><a href="my_subjects.php"><i class="fa-solid fa-book"></i><span>My Subjects</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open-reader"></i><span>Syllabus & Materials</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i><span>Assignments</span></a></li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i><span>Timetable</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i><span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i><span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="faculty_profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="topbar">
        <h2><i class="fa-solid fa-calendar-check" style="color:#2563EB;"></i> Academic Events & Calendar</h2>
        <div class="topbar-right">
            <span style="background: #EFF6FF; border: 1px solid #BFDBFE; color: #1D4ED8; font-size: 11.5px; font-weight: 700; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-laptop-code"></i> Department of Computer Science
            </span>
            <div class="role-badge"><i class="fa-solid fa-chalkboard-user"></i> <?=htmlspecialchars($faculty_name)?></div>
        </div>
    </div>

    <div class="content-body">
        
        <div class="banner">
            <div>
                <h1>College Academic Calendar & Schedules</h1>
                <p>Track university examinations, national symposiums, workshops, and sports meets.</p>
            </div>
            <span style="background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 30px; font-size: 12.5px; font-weight: 700; border: 1px solid rgba(255,255,255,0.25);">
                <i class="fa-regular fa-calendar-days"></i> <?=count($filtered_events)?> Scheduled
            </span>
        </div>

        <!-- Filter Toolbar -->
        <form method="GET" class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?=htmlspecialchars($search)?>" placeholder="Search event by name or venue...">
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

        <!-- Events List -->
        <?php if (empty($filtered_events)): ?>
            <div style="background: white; border: 1px dashed #CBD5E1; border-radius: 16px; padding: 48px 20px; text-align: center;">
                <i class="fa-regular fa-calendar-xmark" style="font-size: 38px; color: #94A3B8; margin-bottom: 12px;"></i>
                <h3 style="font-size: 16px; font-weight: 700; color: #1E293B;">No Events Found</h3>
                <p style="font-size: 13px; color: #64748B; margin-top: 4px;">Adjust your filters to see other scheduled college activities.</p>
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
                            <span style="font-weight: 600; color: #2563EB;">
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
