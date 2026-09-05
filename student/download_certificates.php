<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$user_name  = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Student Name';
$role       = $_SESSION['role'] ?? 'Student';
$dept       = $_SESSION['department'] ?? 'Computer Science & Engineering';
$reg_no     = $_SESSION['reg_no'] ?? 'REG' . rand(10000, 99999);

// Certificate Templates Data
$certificates = [
    [
        'id' => 'bonafide',
        'title' => 'Bonafide Certificate',
        'desc' => 'Official proof of enrollment for Bus Pass, Passport, and Bank Loan purposes.',
        'icon' => 'fa-certificate',
        'color' => '#2563EB'
    ],
    [
        'id' => 'course_completion',
        'title' => 'Course Completion Certificate',
        'desc' => 'Certifies that the student has completed the prescribed degree program.',
        'icon' => 'fa-graduation-cap',
        'color' => '#10B981'
    ],
    [
        'id' => 'conduct',
        'title' => 'Conduct & Character Certificate',
        'desc' => 'Attests the student’s good behavior and character during the study period.',
        'icon' => 'fa-award',
        'color' => '#F59E0B'
    ],
    [
        'id' => 'tc',
        'title' => 'Transfer Certificate (TC)',
        'desc' => 'Official transfer document provided upon course completion or exit.',
        'icon' => 'fa-file-signature',
        'color' => '#8B5CF6'
    ]
];

// Handle Print / Download Preview
$selected_cert = $_GET['print'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Download Certificates - SRMS Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
body { background-color: #F8FAFC; color: #1E293B; display: flex; min-height: 100vh; }

/* Unified Student Sidebar */
.sidebar { width: 260px; height: 100vh; position: fixed; left: 0; top: 0; background: #0B132B; color: white; display: flex; flex-direction: column; justify-content: space-between; z-index: 100; border-right: 1px solid rgba(255,255,255,0.05); }
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

/* Main Content Area */
.main-content { margin-left: 260px; flex-grow: 1; display: flex; flex-direction: column; min-width: 0; min-height: 100vh; }
.topbar { background: #FFFFFF; padding: 16px 36px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; position: sticky; top: 0; z-index: 50; }
.topbar-title h1 { font-size: 20px; font-weight: 700; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge { padding: 5px 12px; border-radius: 30px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; letter-spacing: 0.3px; }

/* Content Body */
.content-body { padding: 30px 36px; }

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: #FFFFFF;
    padding: 20px 24px;
    border-radius: 16px;
    border: 1px solid #E2E8F0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-title {
    display: flex;
    align-items: center;
    gap: 14px;
}

.page-header-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #ECFDF5;
    color: #059669;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.page-header h1 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    letter-spacing: -0.3px;
}

.page-header p {
    font-size: 12.5px;
    color: #64748B;
    margin-top: 2px;
}

/* Certificate Cards Grid */
.cert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 22px;
}

.cert-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.cert-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.06);
    border-color: #CBD5E1;
}

.cert-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 14px;
}

.cert-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: white;
    flex-shrink: 0;
}

.cert-title {
    font-size: 16px;
    font-weight: 700;
    color: #0F172A;
    line-height: 1.3;
}

.cert-desc {
    font-size: 13.5px;
    color: #64748B;
    line-height: 1.6;
    margin-bottom: 24px;
    flex-grow: 1;
}

.btn-download {
    background: #059669;
    color: white;
    border: none;
    padding: 12px 18px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
    width: 100%;
}

.btn-download:hover {
    background: #047857;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
}

/* PRINT TEMPLATE STYLES */
@media print {
    body * { visibility: hidden; }
    #printable-certificate, #printable-certificate * { visibility: visible; }
    #printable-certificate { position: absolute; left: 0; top: 0; width: 100%; height: 100%; padding: 40px; background: white; }
    .no-print { display: none !important; }
}

.cert-template {
    display: none;
    background: #FFF;
    border: 10px double #059669;
    padding: 50px;
    text-align: center;
    position: relative;
    border-radius: 8px;
}

.cert-template h2 {
    color: #0F172A;
    font-size: 26px;
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}

.cert-template h4 {
    color: #059669;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 25px;
    letter-spacing: 1px;
}

.cert-body {
    font-size: 16px;
    color: #334155;
    line-height: 2.2;
    margin: 30px 0;
    text-align: justify;
    text-justify: inter-word;
}

.cert-footer {
    display: flex;
    justify-content: space-between;
    margin-top: 60px;
    padding-top: 20px;
}

.sig-box {
    text-align: center;
    width: 200px;
    border-top: 1px solid #94A3B8;
    padding-top: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
}

@media(max-width: 900px) {
    .sidebar { display: none; }
    .main-content { margin-left: 0; }
    .topbar, .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- Unified Student Sidebar -->
<div class="sidebar no-print">
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

            <li class="nav-category">Academic & Services</li>
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> <span>Timetable</span></a></li>
            <li><a href="assignments.php"><i class="fa-solid fa-file-pen"></i> <span>Assignments</span></a></li>
            <li><a href="student_leave.php"><i class="fa-solid fa-envelope-open-text"></i> <span>Leave / OD Request</span></a></li>
            <li><a href="syllabus_materials.php"><i class="fa-solid fa-book-open"></i> <span>Syllabus & Materials</span></a></li>
            <li><a href="download_certificates.php" class="active"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
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
<div class="main-content no-print">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Academic Certificates & Letters</h1>
            <p>Official digital credentials and verified student certificates</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?=htmlspecialchars($user_name)?>
            </div>
        </div>
    </div>

    <!-- Content Body -->
    <div class="content-body">
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-title">
                <div class="page-header-icon">
                    <i class="fa-solid fa-file-pdf"></i>
                </div>
                <div>
                    <h1>Download Official Certificates</h1>
                    <p>Select any required document to preview and print official certified PDFs instantly</p>
                </div>
            </div>
        </div>

        <div class="cert-grid">
            <?php foreach ($certificates as $cert): ?>
                <div class="cert-card">
                    <div>
                        <div class="cert-header">
                            <div class="cert-icon" style="background: <?php echo $cert['color']; ?>;">
                                <i class="fa-solid <?php echo $cert['icon']; ?>"></i>
                            </div>
                            <div class="cert-title"><?php echo htmlspecialchars($cert['title']); ?></div>
                        </div>
                        <div class="cert-desc"><?php echo htmlspecialchars($cert['desc']); ?></div>
                    </div>
                    <button class="btn-download" onclick="generateCertificate('<?php echo $cert['id']; ?>', '<?php echo htmlspecialchars($cert['title']); ?>')">
                        <i class="fa-solid fa-file-arrow-down"></i> View & Print Certificate
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- PRINTABLE CERTIFICATE TEMPLATE -->
<div id="printable-certificate" class="cert-template">
    <h2>ARIGNAR ANNA GOVERNMENT ARTS COLLEGE</h2>
    <p style="font-size: 13px; color: #64748B;">Approved by AICTE & Affiliated to State University &bull; Villupuram</p>
    <hr style="margin: 20px 0; border: 0; border-top: 2px solid #059669;">
    
    <h4 id="cert-type-title">BONAFIDE CERTIFICATE</h4>
    
    <div class="cert-body">
        This is to certify that <strong>Mr. / Ms. <?php echo htmlspecialchars($user_name); ?></strong> 
        (Register No: <strong><?php echo htmlspecialchars($reg_no); ?></strong>) is a bonafide student of 
        <strong>Arignar Anna Government Arts College</strong>, pursuing degree program in 
        <strong><?php echo htmlspecialchars($dept); ?></strong> during the academic period 2024 - 2027.
        <br><br>
        This certificate is issued upon student request for official and academic documentation purposes.
    </div>

    <div class="cert-footer">
        <div>
            <p style="font-size: 13px; color: #64748B; text-align: left;">Date: <strong><?php echo date('d-m-Y'); ?></strong></p>
            <p style="font-size: 13px; color: #64748B; text-align: left;">Place: <strong>Villupuram</strong></p>
        </div>
        <div class="sig-box">
            Head of Department
        </div>
        <div class="sig-box">
            Principal / Registrar
        </div>
    </div>
</div>

<script>
function generateCertificate(id, title) {
    document.getElementById('cert-type-title').innerText = title.toUpperCase();
    var certElem = document.getElementById('printable-certificate');
    certElem.style.display = 'block';
    
    window.print();
    
    setTimeout(function() {
        certElem.style.display = 'none';
    }, 1000);
}
</script>

</body>
</html>