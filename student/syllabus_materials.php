<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Student Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_name  = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Student';

// Calculate material metrics
$materials = $_SESSION['syllabus_materials'] ?? [];
$total_count = count($materials);
$syllabus_count = count(array_filter($materials, fn($m) => strtolower($m['material_type'] ?? '') === 'syllabus'));
$notes_count = $total_count - $syllabus_count;
$subjects_list = array_unique(array_filter(array_map(fn($m) => $m['subject_code'] ?? '', $materials)));
$unique_subjects_count = count($subjects_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Syllabus & Study Materials - SRMS Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

:root {
    --primary: #059669;
    --primary-dark: #047857;
    --primary-light: #ECFDF5;
    --primary-border: #A7F3D0;
    --sidebar-bg: #0B132B;
    --bg-light: #F8FAFC;
    --card-bg: #FFFFFF;
    --border-color: #E2E8F0;
    --text-dark: #0F172A;
    --text-muted: #64748B;
    --accent-blue: #0284C7;
    --accent-purple: #6366F1;
}

body {
    background-color: var(--bg-light);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    color: #FFFFFF;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 100;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
}

.sidebar-top {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 8px;
}

.logo-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-brand h2 {
    font-size: 17px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.2px;
}

.sidebar-brand span {
    font-size: 11px;
    color: #94A3B8;
    display: block;
    font-weight: 500;
}

.sidebar-menu {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-top: 8px;
    max-height: calc(100vh - 170px);
    overflow-y: auto;
    padding-right: 4px;
}

.sidebar-menu::-webkit-scrollbar {
    width: 4px;
}
.sidebar-menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 9px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-menu li a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}

.sidebar-menu li a.active {
    background: var(--primary);
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}

.sidebar-menu li a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.nav-category {
    font-size: 10.5px;
    text-transform: uppercase;
    color: #64748B;
    font-weight: 700;
    letter-spacing: 0.8px;
    padding: 12px 14px 4px;
}

.sidebar-footer {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.sidebar-footer a:hover {
    background: rgba(255, 255, 255, 0.06);
}

/* Main Content */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 90;
}

.topbar-title h1 {
    font-size: 19px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.topbar-title p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-badge {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--primary-border);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.content-body {
    padding: 30px 40px 60px;
    max-width: 1360px;
    width: 100%;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-dark);
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Stats Row */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-info h4 {
    font-size: 11.5px;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-info h2 {
    font-size: 22px;
    color: var(--text-dark);
    font-weight: 800;
    margin-top: 2px;
}

/* Main Card */
.card {
    background: #FFFFFF;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
}

.card-header {
    padding: 20px 28px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.card-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    min-width: 240px;
}

.search-box i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
    font-size: 13px;
}

.search-box input {
    width: 100%;
    padding: 8px 12px 8px 34px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    font-size: 13px;
    outline: none;
    background: #F8FAFC;
    transition: all 0.2s ease;
}

.search-box input:focus {
    background: #FFFFFF;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}

.filter-pills {
    display: flex;
    gap: 6px;
    background: #F1F5F9;
    padding: 4px;
    border-radius: 10px;
}

.filter-pill {
    padding: 6px 14px;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-pill.active {
    background: #FFFFFF;
    color: var(--text-dark);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

/* Table */
.table-wrapper {
    overflow-x: auto;
}

.sub-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.sub-table th {
    background: #F8FAFC;
    color: #475569;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11.5px;
    letter-spacing: 0.5px;
    padding: 14px 24px;
    border-bottom: 1px solid var(--border-color);
}

.sub-table td {
    padding: 16px 24px;
    font-size: 13.5px;
    border-bottom: 1px solid #F1F5F9;
    color: #334155;
    vertical-align: middle;
}

.sub-table tr:last-child td {
    border-bottom: none;
}

.sub-table tr:hover td {
    background-color: #F8FAFC;
}

.doc-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.doc-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: #F1F5F9;
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.doc-title {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 13.5px;
}

.code-pill {
    display: inline-block;
    font-family: monospace;
    font-weight: 700;
    font-size: 12px;
    color: var(--text-dark);
    background: #F1F5F9;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
}

.badge-type {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11.5px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-syllabus {
    background: #EEF2FF;
    color: #4F46E5;
    border: 1px solid #C7D2FE;
}

.badge-material {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
}

.btn-dl {
    background: var(--primary);
    color: #FFFFFF;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12.5px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
}

.btn-dl:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    color: #CBD5E1;
    margin-bottom: 12px;
}

.empty-state h3 {
    font-size: 16px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
}

.empty-state p {
    font-size: 13px;
}

/* Responsive */
@media (max-width: 1100px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Unified Student Sidebar -->
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
            <li><a href="grievance.php"><i class="fa-solid fa-headset"></i> <span>Student Grievance</span></a></li>

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php" class="active"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
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

<!-- Main Content Wrapper -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Academic Syllabus & Study Materials</h1>
            <p>Course curriculum, lecture notes, reference PDFs, and subject documentation</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <div>
                    <h1>Curriculum & Learning Resources</h1>
                    <p>Enrolled Account: <strong><?php echo htmlspecialchars($user_email); ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #E0F2FE; color: #0284C7;">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Resources</h4>
                    <h2><?php echo $total_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #EEF2FF; color: #4F46E5;">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div class="stat-info">
                    <h4>Course Syllabi</h4>
                    <h2><?php echo $syllabus_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #ECFDF5; color: #059669;">
                    <i class="fa-solid fa-book-bookmark"></i>
                </div>
                <div class="stat-info">
                    <h4>Study Notes</h4>
                    <h2><?php echo $notes_count; ?></h2>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="stat-info">
                    <h4>Subjects Available</h4>
                    <h2><?php echo $unique_subjects_count; ?></h2>
                </div>
            </div>
        </div>

        <!-- Materials Table Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fa-solid fa-layer-group" style="color: var(--primary);"></i>
                    <span>Available Course Materials</span>
                </div>
                <div class="filter-bar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search by title, subject..." onkeyup="filterMaterials()">
                    </div>
                    <div class="filter-pills">
                        <button class="filter-pill active" onclick="setCategoryFilter('all', this)">All</button>
                        <button class="filter-pill" onclick="setCategoryFilter('syllabus', this)">Syllabus</button>
                        <button class="filter-pill" onclick="setCategoryFilter('material', this)">Notes / Materials</button>
                    </div>
                </div>
            </div>
            
            <div class="table-wrapper">
                <table class="sub-table" id="materialsTable">
                    <thead>
                        <tr>
                            <th>Resource Title</th>
                            <th>Subject Code</th>
                            <th>Category</th>
                            <th>Uploaded By</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($materials)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fa-regular fa-folder-open"></i>
                                        <h3>No Course Materials Available</h3>
                                        <p>Study materials or syllabi will appear here once published by faculty.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($materials as $item): ?>
                            <tr class="material-row" data-category="<?php echo strtolower(htmlspecialchars($item['material_type'] ?? 'material')); ?>">
                                <td>
                                    <div class="doc-info">
                                        <div class="doc-icon">
                                            <i class="<?php echo ($item['material_type'] === 'Syllabus') ? 'fa-solid fa-file-lines' : 'fa-solid fa-file-pdf'; ?>"></i>
                                        </div>
                                        <div>
                                            <span class="doc-title"><?php echo htmlspecialchars($item['title'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="code-pill"><?php echo htmlspecialchars($item['subject_code'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge-type <?php echo ($item['material_type'] === 'Syllabus') ? 'badge-syllabus' : 'badge-material'; ?>">
                                        <i class="<?php echo ($item['material_type'] === 'Syllabus') ? 'fa-solid fa-book' : 'fa-solid fa-paperclip'; ?>"></i>
                                        <?php echo htmlspecialchars($item['material_type'] ?? 'Material'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: #475569; font-weight: 500; font-size: 13px;">
                                        <i class="fa-solid fa-user-tie" style="color: #94A3B8; margin-right: 4px;"></i>
                                        <?php echo htmlspecialchars($item['uploaded_by'] ?? 'Faculty'); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?php echo htmlspecialchars($item['file_url']); ?>" download class="btn-dl">
                                        <i class="fa-solid fa-arrow-down-to-bracket"></i> Download
                                    </a>
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
let currentCategory = 'all';

function setCategoryFilter(category, btn) {
    currentCategory = category;
    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    filterMaterials();
}

function filterMaterials() {
    var query = document.getElementById('searchInput').value.toLowerCase();
    var rows = document.querySelectorAll('#materialsTable .material-row');

    rows.forEach(function(row) {
        var rowText = row.innerText.toLowerCase();
        var rowCat = row.getAttribute('data-category') || '';
        
        var matchesQuery = rowText.includes(query);
        var matchesCat = (currentCategory === 'all') || (rowCat.includes(currentCategory));

        if (matchesQuery && matchesCat) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

</body>
</html>