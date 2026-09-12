<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'faculty') {
    // In case session role is stored differently, check user_id
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/college_data.php';

$faculty_name = $_SESSION['username'] ?? 'Faculty Member';
$faculty_dept = 'CS'; // Core active department

$all_circulars = get_college_circulars();
$categories = get_circular_categories();
$departments = get_college_departments();

// Filter logic
$selected_category = $_GET['category'] ?? '';
$selected_dept = $_GET['dept'] ?? '';
$search_query = trim($_GET['search'] ?? '');

$filtered_circulars = array_filter($all_circulars, function($c) use ($selected_category, $selected_dept, $search_query) {
    if ($selected_category !== '' && ($c['category'] ?? '') !== $selected_category) {
        return false;
    }
    if ($selected_dept !== '' && ($c['dept_code'] ?? 'ALL') !== 'ALL' && ($c['dept_code'] ?? '') !== $selected_dept) {
        return false;
    }
    if ($search_query !== '') {
        $text = ($c['title'] ?? '') . ' ' . ($c['reference_no'] ?? '') . ' ' . ($c['content'] ?? '');
        if (stripos($text, $search_query) === false) {
            return false;
        }
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Circulars & Notices | Faculty Portal</title>
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

        /* Page Banner */
        .banner {
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            border-radius: 18px; padding: 26px 32px; color: white; margin-bottom: 25px;
            box-shadow: 0 8px 24px rgba(30, 58, 138, 0.15); display: flex;
            justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;
        }
        .banner h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
        .banner p { font-size: 13.5px; opacity: 0.9; }

        /* Filter Toolbar */
        .toolbar {
            background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px;
            padding: 16px 20px; margin-bottom: 24px; display: flex; gap: 12px;
            flex-wrap: wrap; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }
        .search-box {
            position: relative; flex: 1; min-width: 220px;
        }
        .search-box input {
            width: 100%; padding: 10px 14px 10px 38px; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13.5px; outline: none; transition: 0.2s;
        }
        .search-box input:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .select-filter {
            padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 8px;
            font-size: 13px; font-weight: 600; color: #334155; background: white; outline: none; cursor: pointer;
        }
        .btn-filter {
            padding: 10px 18px; background: #2563EB; color: white; border: none;
            border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-filter:hover { background: #1D4ED8; }
        .btn-reset {
            padding: 10px 16px; background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1;
            border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;
        }

        /* Circular Cards Grid */
        .circular-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px; }
        .circular-card {
            background: white; border: 1px solid #E2E8F0; border-radius: 16px;
            padding: 22px; display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.25s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03); position: relative;
        }
        .circular-card:hover {
            transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.06); border-color: #BFDBFE;
        }
        .card-meta {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;
        }
        .cat-badge {
            font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px;
            border-radius: 20px; letter-spacing: 0.4px;
        }
        .cat-academic { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
        .cat-exam { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .cat-holiday { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .cat-general { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }
        .cat-fee { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }

        .dept-scope-badge {
            font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px;
            background: #F8FAFC; border: 1px solid #E2E8F0; color: #64748B;
        }
        .dept-scope-badge.all { background: #FAF5FF; border-color: #E9D5FF; color: #7C3AED; }

        .circular-title {
            font-size: 16px; font-weight: 700; color: #0F172A; margin-bottom: 8px; line-height: 1.4;
        }
        .circular-snippet {
            font-size: 13px; color: #64748B; line-height: 1.5; margin-bottom: 16px;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
        }
        .card-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 14px; border-top: 1px solid #F1F5F9; font-size: 12px; color: #94A3B8;
        }
        .btn-view-circ {
            padding: 7px 14px; background: #2563EB; color: white; border: none;
            border-radius: 8px; font-size: 12.5px; font-weight: 700; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-view-circ:hover { background: #1D4ED8; }

        /* Modal */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;
        }
        .modal.active { display: flex; }
        .modal-card {
            background: white; border-radius: 20px; max-width: 650px; width: 100%;
            overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: modalIn 0.25s ease;
        }
        @keyframes modalIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-head {
            padding: 22px 28px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body { padding: 28px; max-height: 65vh; overflow-y: auto; }
        .modal-body h2 { font-size: 19px; font-weight: 800; color: #0F172A; margin-bottom: 12px; }
        .modal-body .meta-strip {
            display: flex; gap: 15px; font-size: 12.5px; color: #64748B;
            padding-bottom: 16px; margin-bottom: 16px; border-bottom: 1px solid #F1F5F9;
        }
        .modal-body .circ-text { font-size: 14px; line-height: 1.7; color: #334155; white-space: pre-wrap; }
        .modal-actions {
            padding: 18px 28px; background: #F8FAFC; border-top: 1px solid #E2E8F0;
            display: flex; justify-content: flex-end; gap: 10px;
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

<!-- Unified Faculty Sidebar -->
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
            <li><a href="circulars.php" class="active"><i class="fa-solid fa-bullhorn"></i><span>Circulars & Notices</span></a></li>
            <li><a href="events.php"><i class="fa-solid fa-calendar-check"></i><span>Events & Calendar</span></a></li>

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
        <h2><i class="fa-solid fa-bullhorn" style="color:#2563EB;"></i> Official Circulars & Notices</h2>
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
                <h1>College Circulars & Academic Memorandums</h1>
                <p>Official notices from the Principal's Office and Controller of Examinations for faculty & staff.</p>
            </div>
            <span style="background: rgba(255,255,255,0.18); padding: 8px 16px; border-radius: 30px; font-size: 12.5px; font-weight: 700; border: 1px solid rgba(255,255,255,0.25);">
                <i class="fa-solid fa-bell"></i> <?=count($filtered_circulars)?> Notices Available
            </span>
        </div>

        <!-- Filter Toolbar -->
        <form method="GET" class="toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="search" value="<?=htmlspecialchars($search_query)?>" placeholder="Search circular by title, ref no or keyword...">
            </div>

            <select name="category" class="select-filter">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?=$cat?>" <?=$selected_category === $cat ? 'selected' : ''?>><?=$cat?></option>
                <?php endforeach; ?>
            </select>

            <select name="dept" class="select-filter">
                <option value="">All Targets</option>
                <option value="ALL" <?=$selected_dept === 'ALL' ? 'selected' : ''?>>College-Wide (All)</option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?=$d['code']?>" <?=$selected_dept === $d['code'] ? 'selected' : ''?>><?=htmlspecialchars($d['name'])?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if ($selected_category || $selected_dept || $search_query): ?>
                <a href="circulars.php" class="btn-reset"><i class="fa-solid fa-rotate-left"></i> Reset</a>
            <?php endif; ?>
        </form>

        <!-- Circulars Cards -->
        <?php if (empty($filtered_circulars)): ?>
            <div style="background: white; border: 1px dashed #CBD5E1; border-radius: 16px; padding: 48px 20px; text-align: center;">
                <i class="fa-solid fa-circle-info" style="font-size: 38px; color: #94A3B8; margin-bottom: 12px;"></i>
                <h3 style="font-size: 16px; font-weight: 700; color: #1E293B;">No Circulars Match Your Filter</h3>
                <p style="font-size: 13px; color: #64748B; margin-top: 4px;">Try selecting a different category or clearing the search query.</p>
            </div>
        <?php else: ?>
            <div class="circular-list">
                <?php foreach ($filtered_circulars as $c): 
                    $cat = $c['category'] ?? 'General';
                    $cat_class = 'cat-' . strtolower($cat);
                    $target = ($c['dept_code'] ?? 'ALL') === 'ALL' ? 'All Departments' : htmlspecialchars($c['dept_code']) . ' Dept';
                ?>
                    <div class="circular-card">
                        <div>
                            <div class="card-meta">
                                <span class="cat-badge <?=$cat_class?>"><?=htmlspecialchars($cat)?></span>
                                <span class="dept-scope-badge <?=($c['dept_code'] ?? 'ALL') === 'ALL' ? 'all' : ''?>">
                                    <i class="fa-solid fa-building-columns"></i> <?=$target?>
                                </span>
                            </div>
                            <h3 class="circular-title"><?=htmlspecialchars($c['title'] ?? 'Notice')?></h3>
                            <p class="circular-snippet"><?=htmlspecialchars($c['content'] ?? '')?></p>
                        </div>
                        <div class="card-footer">
                            <div>
                                <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i> <?=htmlspecialchars($c['issued_date'] ?? date('Y-m-d'))?>
                            </div>
                            <button type="button" class="btn-view-circ" onclick='openModal(<?=json_encode($c)?>)'>
                                <i class="fa-solid fa-eye"></i> View Full
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Modal -->
<div class="modal" id="circModal">
    <div class="modal-card">
        <div class="modal-head">
            <span id="mCategory" class="cat-badge cat-academic">Academic</span>
            <button onclick="closeModal()" style="background:none; border:none; font-size:18px; color:#64748B; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body">
            <h2 id="mTitle">Circular Title</h2>
            <div class="meta-strip">
                <span><i class="fa-solid fa-hashtag"></i> <span id="mRef">Ref No</span></span>
                <span><i class="fa-regular fa-calendar"></i> <span id="mDate">Date</span></span>
                <span><i class="fa-solid fa-building-columns"></i> <span id="mScope">All Departments</span></span>
            </div>
            <div class="circ-text" id="mContent">Content goes here...</div>
        </div>
        <div class="modal-actions">
            <button type="button" onclick="window.print()" class="btn-reset" style="cursor:pointer;"><i class="fa-solid fa-print"></i> Print</button>
            <button type="button" onclick="closeModal()" class="btn-filter" style="cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
function openModal(data) {
    document.getElementById('mTitle').innerText = data.title || 'Circular';
    document.getElementById('mRef').innerText = data.reference_no || 'OFFICIAL';
    document.getElementById('mDate').innerText = data.issued_date || '<?=date('Y-m-d')?>';
    document.getElementById('mScope').innerText = (data.dept_code === 'ALL' || !data.dept_code) ? 'All Departments' : data.dept_code + ' Department';
    document.getElementById('mContent').innerText = data.content || '';
    
    const catSpan = document.getElementById('mCategory');
    catSpan.innerText = data.category || 'General';
    catSpan.className = 'cat-badge cat-' + (data.category ? data.category.toLowerCase() : 'general');
    
    document.getElementById('circModal').classList.add('active');
}

function closeModal() {
    document.getElementById('circModal').classList.remove('active');
}

window.onclick = function(e) {
    if (e.target.id === 'circModal') {
        closeModal();
    }
}
</script>

</body>
</html>
