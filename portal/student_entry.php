<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/college_data.php';

$preset_dept = strtoupper(trim($_GET['dept'] ?? ''));
$preset_year = strtoupper(trim($_GET['year'] ?? ''));

$departments = get_all_departments();
$errors = $_SESSION['form_errors'] ?? [];
$prev_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

function val($key, $default = '') {
    global $prev_data;
    return htmlspecialchars((string)($prev_data[$key] ?? $default));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Comprehensive Student Registration Portal - Arignar Anna Government Arts College</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #1E3A8A;
    --primary-light: #2563EB;
    --accent: #F59E0B;
    --bg-main: #F1F5F9;
    --card-bg: #FFFFFF;
    --border: #CBD5E1;
    --text-dark: #0F172A;
    --text-muted: #64748B;
    --success: #10B981;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {
    background-color: var(--bg-main);
    color: var(--text-dark);
    min-height: 100vh;
    padding-bottom: 70px;
}

/* Header */
.portal-header {
    background: #0B132B;
    color: white;
    padding: 30px 20px;
    text-align: center;
    border-bottom: 4px solid var(--primary-light);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.portal-header .brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.08);
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    color: var(--accent);
    font-weight: 700;
    margin-bottom: 10px;
}
.portal-header h1 {
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.5px;
    margin-bottom: 6px;
}
.portal-header p {
    font-size: 14px;
    color: #94A3B8;
    max-width: 700px;
    margin: 0 auto;
}

/* Container */
.portal-container {
    max-width: 960px;
    margin: -25px auto 0 auto;
    padding: 0 16px;
}

/* Wizard Steps Bar */
.steps-nav {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: white;
    border-radius: 16px;
    padding: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
    border: 1px solid var(--border);
    margin-bottom: 22px;
    gap: 8px;
}
.step-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 12px 10px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.25s ease;
    border: 1.5px solid transparent;
    background: #F8FAFC;
    color: #64748B;
}
.step-item.active {
    background: #EFF6FF;
    border-color: #2563EB;
    color: #1E3A8A;
    font-weight: 700;
}
.step-item.done {
    background: #F0FDF4;
    border-color: #BBF7D0;
    color: #166534;
}
.step-item .step-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 13px;
    background: #E2E8F0;
    color: #475569;
}
.step-item.active .step-num {
    background: #2563EB;
    color: white;
}
.step-item.done .step-num {
    background: #10B981;
    color: white;
}
.step-item .step-title {
    font-size: 13px;
    font-weight: 700;
}

/* Card & Sections */
.form-card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    padding: 28px 24px;
}

.step-section {
    display: none;
}
.step-section.active {
    display: block;
}

.section-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    padding-bottom: 14px;
    border-bottom: 2px solid #F1F5F9;
}
.section-banner h2 {
    font-size: 18px;
    font-weight: 800;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}
.mandatory-pill {
    background: #FEF3C7;
    color: #92400E;
    font-size: 11.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
}

.sub-section {
    background: #FAFBFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 18px 20px;
    margin-bottom: 22px;
}
.sub-section-title {
    font-size: 13.5px;
    font-weight: 800;
    color: #1E3A8A;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #E2E8F0;
    padding-bottom: 8px;
}

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.grid-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 14px;
}
.grid-4 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
}

.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #334155;
    margin-bottom: 5px;
}
.form-group label .req {
    color: #EF4444;
    font-weight: 800;
}

input, select, textarea {
    width: 100%;
    padding: 10px 12px;
    font-size: 13px;
    color: #0F172A;
    background: #FFFFFF;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    outline: none;
    transition: all 0.2s ease;
}
input:focus, select:focus, textarea:focus {
    border-color: var(--primary-light);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
textarea {
    resize: vertical;
    min-height: 60px;
}
.highlight-field {
    background: #F0FDF4 !important;
    border-color: #86EFAC !important;
    font-weight: 700;
}

/* Photo Upload / Preview Container */
.photo-upload-row {
    display: flex;
    gap: 16px;
    align-items: center;
    margin-bottom: 14px;
    background: white;
    padding: 12px;
    border-radius: 10px;
    border: 1px dashed #CBD5E1;
}
.photo-preview-box {
    width: 90px;
    height: 110px;
    border-radius: 8px;
    background: #E2E8F0;
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94A3B8;
    font-size: 28px;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid #CBD5E1;
}

/* Semester Marks Tabs & Table */
.sem-tabs-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    overflow-x: auto;
    padding-bottom: 6px;
}
.sem-tab-btn {
    padding: 9px 16px;
    background: #F1F5F9;
    border: 1.5px solid #CBD5E1;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.sem-tab-btn:hover {
    background: #E2E8F0;
    color: #0F172A;
}
.sem-tab-btn.active {
    background: #2563EB;
    border-color: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 10px rgba(37, 99, 235, 0.25);
}
.marks-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 6px;
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
    background: #F8FAFC;
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
.marks-table input:focus {
    border-color: #2563EB;
}
.btn-add {
    background: #059669;
    color: white;
    border: none;
    padding: 7px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 700;
    font-size: 12px;
    transition: background 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-add:hover { background: #047857; }
.btn-delete {
    background: #EF4444;
    color: white;
    border: none;
    padding: 5px 9px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.2s;
}
.btn-delete:hover { background: #DC2626; }
.pf-pass { color: #059669 !important; font-weight: 800; }
.pf-fail { color: #DC2626 !important; font-weight: 800; }

/* School Table */
.school-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    overflow: hidden;
}
.school-table th, .school-table td {
    padding: 9px 10px;
    border-bottom: 1px solid #E2E8F0;
    font-size: 12.5px;
}
.school-table th {
    background: #F8FAFC;
    font-weight: 700;
    color: #334155;
    text-align: left;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 25px;
    padding-top: 18px;
    border-top: 1.5px solid #F1F5F9;
    gap: 12px;
}
.btn {
    padding: 12px 24px;
    font-size: 13.5px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}
.btn-prev {
    background: #FFFFFF;
    color: #475569;
    border: 1.5px solid var(--border);
}
.btn-prev:hover {
    background: #F8FAFC;
    color: #0F172A;
}
.btn-next {
    background: var(--primary-light);
    color: white;
}
.btn-next:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
}
.btn-submit {
    background: var(--success);
    color: white;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
    font-size: 14.5px;
}
.btn-submit:hover {
    background: #059669;
    transform: translateY(-2px);
}

/* Errors Alert */
.alert-error {
    background: #FEF2F2;
    border: 1px solid #FCA5A5;
    color: #991B1B;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13.5px;
}
.alert-error ul {
    margin-left: 20px;
    margin-top: 6px;
}

@media (max-width: 768px) {
    .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
    .steps-nav { grid-template-columns: 1fr; }
    .form-card { padding: 20px 14px; }
    .portal-header h1 { font-size: 20px; }
    .photo-upload-row { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>

<!-- Header -->
<header class="portal-header">
    <div class="brand-badge">
        <i class="fa-solid fa-graduation-cap"></i> Complete Student Registration
    </div>
    <h1>🎓 Arignar Anna Government Arts College</h1>
    <p>Villupuram, Tamil Nadu • Unified Portal for Student Record, Bio Data & UMIS Details</p>
</header>

<div class="portal-container">
    
    <!-- Steps Navigation -->
    <div class="steps-nav">
        <div class="step-item active" id="stepTab1" onclick="switchStep(1)">
            <div class="step-num">1</div>
            <div class="step-title">Student Record (Photo + 19 Fields)</div>
        </div>
        <div class="step-item" id="stepTab2" onclick="validateAndGoStep(2)">
            <div class="step-num">2</div>
            <div class="step-title">Bio Data Details</div>
        </div>
        <div class="step-item" id="stepTab3" onclick="validateAndGoStep(3)">
            <div class="step-num">3</div>
            <div class="step-title">UMIS Information</div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert-error">
        <strong><i class="fa-solid fa-triangle-exclamation"></i> Please fix the following errors:</strong>
        <ul>
            <?php foreach ($errors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <form action="process_entry.php" method="POST" enctype="multipart/form-data" id="unifiedStudentForm">

            <!-- ==========================================
                 STEP 1: STUDENT RECORD (PHOTO + 19 FIELDS)
                 ========================================== -->
            <div class="step-section active" id="stepSection1">
                <div class="section-banner">
                    <h2><i class="fa-solid fa-user-graduate" style="color:#2563EB;"></i> Step 1: Student Record (Admission Form)</h2>
                    <span class="mandatory-pill">* Reg No, Email & Core Details</span>
                </div>

                <!-- STUDENT PHOTO -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-camera"></i> Student Photo Upload</div>
                    <div class="photo-upload-row">
                        <img id="photo_preview" src="https://via.placeholder.com/130x160?text=Upload+Photo" class="photo-preview-box" alt="Student Photo Preview">
                        <div style="flex:1;">
                            <label style="font-size:13px; font-weight:700; color:#1E3A8A; margin-bottom:6px; display:block;">Select Student Passport Size Photo <span class="req">*</span></label>
                            <input type="file" name="student_photo_file" id="field_student_photo_file" accept="image/jpeg,image/png,image/jpg,image/webp" required onchange="previewFile(this, 'photo_preview')" style="padding:8px 12px; background:white; border:1.5px dashed #2563EB; border-radius:8px; cursor:pointer; width:100%;">
                            <span style="font-size:11.5px; color:#64748B; margin-top:5px; display:block;"><i class="fa-solid fa-circle-info"></i> Direct photo file upload (JPG, PNG, WEBP). Preview appears immediately.</span>
                        </div>
                    </div>
                </div>

                <!-- BASIC IDENTIFICATION (FIELDS 1-4) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-id-card"></i> 1. Basic Identification</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>1. Student Full Name <span class="req">*</span></label>
                            <input type="text" name="name" id="field_name" required placeholder="Enter Full Name (as in TC / 12th Marksheet)" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>2. Date of Birth <span class="req">*</span></label>
                            <input type="date" name="dob" id="field_dob" required onchange="syncSharedFields()">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>3. Admission Number <span class="req">*</span></label>
                            <input type="text" name="admission_no" id="field_admission_no" required placeholder="College Admission Number" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>4. Roll Number <span class="req">*</span></label>
                            <input type="text" name="roll_no" id="field_roll_no" required placeholder="Class Roll No (e.g. 24CSC125)" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>12. Examination Register Number <span class="req">*</span></label>
                            <input type="text" name="exam_reg_no" id="field_exam_reg_no" class="highlight-field" required placeholder="University Exam Reg No" oninput="syncSharedFields()">
                            <input type="hidden" name="reg_no" id="field_reg_no">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>Student Email Address <span class="req">*</span></label>
                            <input type="email" name="email" id="field_email" class="highlight-field" required placeholder="student@aagacvpm.edu.in" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>Student Mobile Number <span class="req">*</span></label>
                            <input type="tel" name="student_phone" id="field_student_phone" required placeholder="10-digit mobile number" oninput="syncSharedFields()">
                        </div>
                    </div>
                </div>

                <!-- PARENTS DETAILS & ADDRESS (FIELDS 5-6) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-users"></i> 2. Parent & Address Details</div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>5. Parent's Name <span class="req">*</span></label>
                            <input type="text" name="parent_name" id="field_parent_name" required placeholder="Father / Mother Name" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>Parent's Occupation</label>
                            <input type="text" name="parent_occupation" id="field_parent_occupation" placeholder="Occupation / Business / Farmer" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>Parent's Phone Number <span class="req">*</span></label>
                            <input type="tel" name="parent_phone" id="field_parent_phone" required placeholder="Parent Contact Number" oninput="syncSharedFields()">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Parent's Communication Address</label>
                        <textarea name="parent_address" id="field_parent_address" placeholder="Door No, Street, Village/Town, District, Pincode" oninput="syncSharedFields()"></textarea>
                    </div>

                    <div class="form-group">
                        <label>6. Permanent Address <span class="req">*</span></label>
                        <textarea name="permanent_address" id="field_permanent_address" required placeholder="Permanent Native Home Address" oninput="syncSharedFields()"></textarea>
                    </div>
                </div>

                <!-- COURSE & ACADEMIC DETAILS (FIELDS 7-12) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-graduation-cap"></i> 3. Course & Academic Information</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>7. Course / Department <span class="req">*</span></label>
                            <select name="course" id="field_course" required onchange="syncAcademicClass()">
                                <optgroup label="Arts & Commerce">
                                    <option value="TAM" <?php echo ($preset_dept === 'TAM' ? 'selected' : ''); ?>>TAM - Department of Tamil</option>
                                    <option value="ENG" <?php echo ($preset_dept === 'ENG' ? 'selected' : ''); ?>>ENG - Department of English</option>
                                    <option value="HIST" <?php echo ($preset_dept === 'HIST' ? 'selected' : ''); ?>>HIST - Department of History</option>
                                    <option value="ECO" <?php echo ($preset_dept === 'ECO' ? 'selected' : ''); ?>>ECO - Department of Economics</option>
                                    <option value="COMM" <?php echo ($preset_dept === 'COMM' ? 'selected' : ''); ?>>COMM - Department of Commerce</option>
                                </optgroup>
                                <optgroup label="Science & IT">
                                    <option value="MATH" <?php echo ($preset_dept === 'MATH' ? 'selected' : ''); ?>>MATH - Department of Mathematics</option>
                                    <option value="PHY" <?php echo ($preset_dept === 'PHY' ? 'selected' : ''); ?>>PHY - Department of Physics</option>
                                    <option value="CHEM" <?php echo ($preset_dept === 'CHEM' ? 'selected' : ''); ?>>CHEM - Department of Chemistry</option>
                                    <option value="BOT" <?php echo ($preset_dept === 'BOT' ? 'selected' : ''); ?>>BOT - Department of Botany</option>
                                    <option value="ZOO" <?php echo ($preset_dept === 'ZOO' ? 'selected' : ''); ?>>ZOO - Department of Zoology</option>
                                    <option value="STAT" <?php echo ($preset_dept === 'STAT' ? 'selected' : ''); ?>>STAT - Department of Statistics</option>
                                    <option value="CS" <?php echo ($preset_dept === 'CS' || empty($preset_dept) ? 'selected' : ''); ?>>CS - Department of Computer Science</option>
                                    <option value="BCA" <?php echo ($preset_dept === 'BCA' ? 'selected' : ''); ?>>BCA - Department of Computer Applications</option>
                                    <option value="IT" <?php echo ($preset_dept === 'IT' ? 'selected' : ''); ?>>IT - Department of Information Technology</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>8. Degree Level & Academic Year <span class="req">*</span></label>
                            <select name="academic_year_level" id="field_year_level" required onchange="syncAcademicClass()">
                                <option value="UG_1" <?php echo ($preset_year === 'UG_1' ? 'selected' : ''); ?>>UG - 1st Year (I Year)</option>
                                <option value="UG_2" <?php echo ($preset_year === 'UG_2' ? 'selected' : ''); ?>>UG - 2nd Year (II Year)</option>
                                <option value="UG_3" <?php echo ($preset_year === 'UG_3' || empty($preset_year) ? 'selected' : ''); ?>>UG - 3rd Year (III Year)</option>
                                <option value="PG_1" <?php echo ($preset_year === 'PG_1' ? 'selected' : ''); ?>>PG - 1st Year (I Year PG)</option>
                                <option value="PG_2" <?php echo ($preset_year === 'PG_2' ? 'selected' : ''); ?>>PG - 2nd Year (II Year PG)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>9. Main Subject / Major Degree Title <span class="req">*</span></label>
                            <input type="text" name="main_subject" id="field_main_subject" required placeholder="e.g. III B.Sc Computer Science">
                        </div>
                        <div class="form-group">
                            <label>10. Medium of Instruction</label>
                            <select name="medium" id="field_medium">
                                <option value="English">English</option>
                                <option value="Tamil">Tamil</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>11. Ancillary / Allied Subjects</label>
                            <input type="text" name="ancillary_subjects" placeholder="e.g. Mathematics, Physics, Statistics">
                        </div>
                    </div>

                    <div class="grid-2" style="margin-top: 6px;">
                        <div class="form-group">
                            <label>Current Enrolled Semester <span class="req">*</span></label>
                            <select name="current_semester" id="field_current_semester" required onchange="onSemesterChange(this.value)">
                                <option value="Semester 1">Semester 1 (1st Year - Odd)</option>
                                <option value="Semester 2">Semester 2 (1st Year - Even)</option>
                                <option value="Semester 3">Semester 3 (2nd Year - Odd)</option>
                                <option value="Semester 4">Semester 4 (2nd Year - Even)</option>
                                <option value="Semester 5" selected>Semester 5 (3rd Year - Odd)</option>
                                <option value="Semester 6">Semester 6 (3rd Year - Even)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SEMESTER MARKS (SEMESTERS 1 TO 6) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-graduation-cap"></i> 4. Semester Marks & Examination Results (Semesters 1 to 6)</div>
                    <p style="font-size:12.5px; color:#64748B; margin-bottom:14px;">
                        Click each semester tab below to enter or view subject marks. Pass criteria: <strong>U.E ≥ 30 & Total ≥ 40</strong>. Total and Result are calculated automatically in real time.
                    </p>

                    <div class="sem-tabs-bar">
                        <button type="button" class="sem-tab-btn active" id="semTabBtn_1" onclick="switchSemTab(1)">Semester 1</button>
                        <button type="button" class="sem-tab-btn" id="semTabBtn_2" onclick="switchSemTab(2)">Semester 2</button>
                        <button type="button" class="sem-tab-btn" id="semTabBtn_3" onclick="switchSemTab(3)">Semester 3</button>
                        <button type="button" class="sem-tab-btn" id="semTabBtn_4" onclick="switchSemTab(4)">Semester 4</button>
                        <button type="button" class="sem-tab-btn" id="semTabBtn_5" onclick="switchSemTab(5)">Semester 5</button>
                        <button type="button" class="sem-tab-btn" id="semTabBtn_6" onclick="switchSemTab(6)">Semester 6</button>
                    </div>

                    <?php for ($s = 1; $s <= 6; $s++): ?>
                    <div class="sem-pane <?=($s === 1 ? 'active' : '')?>" id="semPane_<?=$s?>" style="<?=($s === 1 ? '' : 'display:none;')?>">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <h4 style="font-size:13.5px; color:#1E3A8A; font-weight:700;"><i class="fa-solid fa-book-bookmark"></i> Semester <?=$s?> Subjects & Examination Marks</h4>
                            <button type="button" class="btn-add" onclick="addSubjectRow(<?=$s?>)"><i class="fa-solid fa-plus"></i> Add Subject</button>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="marks-table">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; width:40%;">Subject Name</th>
                                        <th style="width:14%;">U.E Marks (75)</th>
                                        <th style="width:14%;">I.A Marks (25)</th>
                                        <th style="width:12%;">Total (100)</th>
                                        <th style="width:12%;">Result</th>
                                        <th style="width:8%;">Action</th>
                                     </tr>
                                </thead>
                                <tbody id="sem<?=$s?>_body">
                                    <?php for ($r = 1; $r <= 3; $r++): ?>
                                    <tr>
                                        <td style="text-align:left;"><input type="text" name="sem<?=$s?>_subject[]" value="Subject <?=$r?>" placeholder="Subject Name"></td>
                                        <td><input type="number" name="sem<?=$s?>_ue[]" value="" placeholder="0" min="0" max="75" oninput="calculateMarks(this)"></td>
                                        <td><input type="number" name="sem<?=$s?>_ia[]" value="" placeholder="0" min="0" max="25" oninput="calculateMarks(this)"></td>
                                        <td><input type="number" name="sem<?=$s?>_total[]" value="" readonly style="background:#F8FAFC; font-weight:700;"></td>
                                        <td><input type="text" name="sem<?=$s?>_pf[]" value="" readonly style="background:#F8FAFC; font-weight:700;"></td>
                                        <td><button type="button" class="btn-delete" title="Delete Row" onclick="removeRow(this)">🗑️</button></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- ADMISSION & QUALIFYING DETAILS (FIELDS 13-18) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-file-signature"></i> 5. Admission History & Background</div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>13. Date of Joining</label>
                            <input type="date" name="date_of_joining" id="field_date_joining" onchange="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>14. Date of Leaving (if applicable)</label>
                            <input type="date" name="date_of_leaving">
                        </div>
                        <div class="form-group">
                            <label>15. Community <span class="req">*</span></label>
                            <select name="community" id="field_community" required onchange="syncSharedFields()">
                                <option value="BC">BC</option>
                                <option value="BCM">BCM</option>
                                <option value="MBC">MBC</option>
                                <option value="SC">SC</option>
                                <option value="SCA">SCA</option>
                                <option value="ST">ST</option>
                                <option value="OC">OC</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>16. Part I Language</label>
                            <input type="text" name="part1_language" value="Tamil" placeholder="e.g. Tamil / French / Hindi">
                        </div>
                        <div class="form-group">
                            <label>17. Exam Passed at Admission</label>
                            <input type="text" name="exam_at_admission" placeholder="e.g. HSC / +2 / Diploma">
                        </div>
                        <div class="form-group">
                            <label>18. College / School Last Studied</label>
                            <input type="text" name="last_college" placeholder="Name of School or Institution">
                        </div>
                    </div>
                </div>

                <!-- OPTIONAL CO-CURRICULAR & ATTENDANCE -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-chart-line"></i> 5. Attendance & Activities (Optional)</div>
                    <div class="grid-4">
                        <div class="form-group"><label>Days Present</label><input type="number" name="days_present" placeholder="0"></div>
                        <div class="form-group"><label>Working Days</label><input type="number" name="working_days" placeholder="90"></div>
                        <div class="form-group"><label>Class Rank</label><input type="number" name="class_rank" placeholder="e.g. 5"></div>
                        <div class="form-group"><label>Final Exam Result</label><input type="text" name="final_exam_result" placeholder="e.g. First Class / Pass"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Community Service (NSS / YRC / RRC)</label><input type="text" name="community_service" placeholder="e.g. NSS Volunteer"></div>
                        <div class="form-group"><label>Special Activities / Sports</label><input type="text" name="special_activities" placeholder="e.g. Athletics / Chess"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>Scholarships / Concessions</label><textarea name="scholarships" rows="2" placeholder="e.g. Post-Matric SC/ST / BC scholarship"></textarea></div>
                        <div class="form-group"><label>College Prizes Awarded</label><textarea name="prizes" rows="2" placeholder="Any academic or sports prizes"></textarea></div>
                    </div>
                </div>

                <!-- CHARACTER & ASSESSMENT (FIELDS 15-20 FROM ADMISSION RECORD) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-award"></i> 6. Character & Assessment</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>15. Ability for Independent Work</label>
                            <input type="text" name="independent_work" placeholder="e.g. Good / Satisfactory / Excellent">
                        </div>
                        <div class="form-group">
                            <label>16. Conduct</label>
                            <input type="text" name="conduct" value="Good" placeholder="e.g. Exemplary / Good">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>17. Co-operation</label>
                            <input type="text" name="cooperation" value="Good" placeholder="e.g. Very Good / Good">
                        </div>
                        <div class="form-group">
                            <label>18. Leadership</label>
                            <input type="text" name="leadership" placeholder="e.g. Good / Active">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>19. Overall Assessment</label>
                            <select name="overall_assessment">
                                <option value="Outstanding">Outstanding</option>
                                <option value="Very Good">Very Good</option>
                                <option value="Good" selected>Good</option>
                                <option value="Satisfactory">Satisfactory</option>
                                <option value="Ordinary">Ordinary</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>20. Head of the Department Name</label>
                            <input type="text" name="hod_name" placeholder="HOD Name">
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="justify-content: flex-end;">
                    <button type="button" class="btn btn-next" onclick="validateAndGoStep(2)">
                        Next: Bio Data Details <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ==========================================
                 STEP 2: BIO DATA DETAILS (19 Core Fields)
                 ========================================== -->
            <div class="step-section" id="stepSection2">
                <div class="section-banner">
                    <h2><i class="fa-solid fa-id-card" style="color:#2563EB;"></i> Step 2: Bio Data Details (All 19 Fields)</h2>
                    <span class="mandatory-pill">Tamil Details, EMIS, Caste & 19 Official Bio Data Fields</span>
                </div>

                <!-- PHOTOS & BASIC ACADEMIC PROFILE -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-image"></i> Photos & Basic Academic Details</div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>Tamil Registration Number</label>
                            <input type="text" name="tamil_reg_no" id="bio_tamil_reg_no" placeholder="Tamil Reg No / Exam Reg No">
                        </div>
                        <div class="form-group">
                            <label>Academic Year / Batch</label>
                            <input type="text" name="academic_year" id="bio_academic_year" value="2024-2027" placeholder="e.g. 2024-2027">
                        </div>
                        <div class="form-group">
                            <label>Class Designation</label>
                            <input type="text" name="class" id="bio_class_input" readonly style="background:#F8FAFC; font-weight:700;" value="III B.Sc">
                        </div>
                    </div>

                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #CBD5E1;">
                        <div class="photo-upload-row" style="margin-bottom:0;">
                            <img id="parents_photo_preview" src="https://via.placeholder.com/130x160?text=Parents+Photo" class="photo-preview-box" alt="Parents Photo Preview">
                            <div style="flex:1;">
                                <label style="font-size:13px; font-weight:700; color:#334155; margin-bottom:6px; display:block;">Parents Passport Size Photo (Optional File Upload)</label>
                                <input type="file" name="parents_photo_file" id="field_parents_photo_file" accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewFile(this, 'parents_photo_preview')" style="padding:8px 12px; background:white; border:1.5px dashed #CBD5E1; border-radius:8px; cursor:pointer; width:100%;">
                                <span style="font-size:11.5px; color:#64748B; margin-top:5px; display:block;"><i class="fa-solid fa-circle-check" style="color:#10B981;"></i> Student photo uploaded in Step 1 is automatically attached to Bio Data. Upload parents photo here if available.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PERSONAL DETAILS (FIELDS 1 - 7) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-user-check"></i> Personal Details (Fields 1 to 7)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>1. Student Name (Tamil & English) <span class="req">*</span></label>
                            <input type="text" name="name_ta_en" id="bio_name_ta_en" placeholder="e.g. Kavin S (கவின் ச)">
                        </div>
                        <div class="form-group">
                            <label>2. Father's Name, Mother's Name (in Tamil) <span class="req">*</span></label>
                            <input type="text" name="parents_name_ta" id="bio_parents_name_ta" placeholder="தந்தை, தாய் பெயர் (தமிழ்)">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>3. Date of Birth <span class="req">*</span></label>
                            <input type="date" name="bio_dob" id="bio_dob" onchange="syncBioDobToMain(this.value)">
                        </div>
                        <div class="form-group">
                            <label>4. Community <span class="req">*</span></label>
                            <select name="bio_community" id="bio_community" onchange="syncBioCommunityToMain(this.value)">
                                <option value="">Select Community</option>
                                <option value="OC">OC</option>
                                <option value="BC">BC</option>
                                <option value="BCM">BCM</option>
                                <option value="MBC">MBC</option>
                                <option value="SC">SC</option>
                                <option value="SCA">SCA</option>
                                <option value="ST">ST</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>5. Caste <span class="req">*</span></label>
                            <input type="text" name="caste" id="bio_caste" placeholder="e.g. Vanniyar / Adi Dravidar" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>6. EMIS Number</label>
                            <input type="text" name="emis_no" id="bio_emis_no" placeholder="14-digit EMIS ID" oninput="syncSharedFields()">
                        </div>
                        <div class="form-group">
                            <label>7. Aadhaar Number (12 Digits) <span class="req">*</span></label>
                            <input type="text" name="aadhaar_no" id="bio_aadhaar_no" maxlength="12" placeholder="12-digit Aadhaar Number" oninput="syncSharedFields()">
                        </div>
                    </div>
                </div>

                <!-- FAMILY & SOCIO-ECONOMIC DETAILS (FIELDS 8 - 14) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-hand-holding-dollar"></i> Family & Socio-Economic Details (Fields 8 to 14)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>8. Parent's Occupation</label>
                            <input type="text" name="bio_parent_occupation" id="bio_parent_occupation" placeholder="Parent's Occupation" oninput="syncBioOccToMain(this.value)">
                        </div>
                        <div class="form-group">
                            <label>9. Parent's Annual Income (₹)</label>
                            <input type="number" name="parent_income" id="bio_parent_income" placeholder="e.g. 75000" oninput="syncSharedFields()">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>10. Accommodation <span class="req">*</span></label>
                            <select name="accommodation" id="bio_accommodation">
                                <option value="Day Scholar">Day Scholar</option>
                                <option value="Hostel">Hostel</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>11. Travel Concession Required <span class="req">*</span></label>
                            <select name="travel_concession" id="bio_travel_concession">
                                <option value="Yes">Yes</option>
                                <option value="No">No</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>12. Nearest Scholarship Office</label>
                            <input type="text" name="scholarship_office" id="bio_scholarship_office" placeholder="e.g. DWO Villupuram / Tahsildar Office">
                        </div>
                        <div class="form-group">
                            <label>13. Scholarship Details</label>
                            <input type="text" name="scholarship_details" id="bio_scholarship_details" placeholder="e.g. Post-Matric / First Graduate / Pudhumai Penn">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>14. Previous Year's Attendance %</label>
                        <input type="text" name="prev_attendance" id="bio_prev_attendance" placeholder="e.g. 85">
                    </div>
                </div>

                <!-- CONTACT & ADDRESS (FIELDS 15 - 19) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-address-book"></i> Contact & Address (Fields 15 to 19)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Student Phone <span class="req">*</span></label>
                            <input type="tel" name="bio_student_phone" id="bio_student_phone" placeholder="Student Phone" oninput="syncBioPhoneToMain(this.value)">
                        </div>
                        <div class="form-group">
                            <label>Parent's Phone <span class="req">*</span></label>
                            <input type="tel" name="bio_parent_phone" id="bio_parent_phone" placeholder="Parent's Phone" oninput="syncBioParentPhoneToMain(this.value)">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>15. Contact Address</label>
                        <textarea name="contact_address" id="bio_contact_address" rows="2" placeholder="Contact / Communication Address"></textarea>
                    </div>

                    <div class="form-group">
                        <label>16. Permanent Address <span class="req">*</span></label>
                        <textarea name="bio_permanent_address" id="bio_permanent_address" rows="2" placeholder="Permanent Native Home Address" oninput="syncBioPermAddrToMain(this.value)"></textarea>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>17. Child of Ex-Serviceman? <span class="req">*</span></label>
                            <select name="ex_serviceman" id="bio_ex_serviceman">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>18. Differently Abled? (Disability)</label>
                            <input type="text" name="disability" id="bio_disability" placeholder="If Yes, specify type or leave blank">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>19. Achievements / Sports / NCC / NSS</label>
                        <textarea name="achievements" id="bio_achievements" rows="2" placeholder="State/District Level Sports, NCC C-Certificate, NSS awards, etc."></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-prev" onclick="switchStep(1)">
                        <i class="fa-solid fa-arrow-left"></i> Back: Student Record
                    </button>
                    <button type="button" class="btn btn-next" onclick="validateAndGoStep(3)">
                        Next: UMIS Information <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- ==========================================
                 STEP 3: UMIS INFORMATION (All 80 Items)
                 ========================================== -->
            <div class="step-section" id="stepSection3">
                <div class="section-banner">
                    <h2><i class="fa-solid fa-building-columns" style="color:#2563EB;"></i> Step 3: UMIS Information (All 80 Items)</h2>
                    <span class="mandatory-pill">Tamil Nadu Higher Education UMIS Portal Integration</span>
                </div>

                <!-- 1. COLLEGE INFORMATION (ITEMS 1 - 4) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-landmark"></i> 1. College Information (Items 1 to 4)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>1. College Name</label>
                            <input type="text" name="college_name" readonly style="background:#F8FAFC;" value="Arignar Anna Government Arts College">
                        </div>
                        <div class="form-group">
                            <label>2. College Code (UMIS Code)</label>
                            <input type="text" name="college_code" readonly style="background:#F8FAFC;" value="G105">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>3. College District</label>
                            <input type="text" name="college_district" readonly style="background:#F8FAFC;" value="Villupuram">
                        </div>
                        <div class="form-group">
                            <label>4. College Region</label>
                            <select name="college_region">
                                <option value="Vellore" selected>Vellore</option>
                                <option value="Chennai">Chennai</option>
                                <option value="Coimbatore">Coimbatore</option>
                                <option value="Cuddalore">Cuddalore</option>
                                <option value="Dharmapuri">Dharmapuri</option>
                                <option value="Madurai">Madurai</option>
                                <option value="Thanjavur">Thanjavur</option>
                                <option value="Trichy">Trichy</option>
                                <option value="Tirunelveli">Tirunelveli</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2. GENERAL & CERTIFICATE DETAILS (ITEMS 5 - 21) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-user-shield"></i> 2. General Information (Items 5 to 21)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>5. Student EMIS ID</label>
                            <input type="text" name="umis_emis_id" id="umis_emis_id" placeholder="14-digit EMIS ID">
                        </div>
                        <div class="form-group">
                            <label>6. If No EMIS ID, Reason</label>
                            <select name="no_emis_reason">
                                <option value="">--Select / Have EMIS ID--</option>
                                <option value="Other State">Other State</option>
                                <option value="Studied before 2018">Studied before 2018</option>
                                <option value="ICSE">ICSE</option>
                                <option value="Open School">Open School</option>
                                <option value="Tutorial">Tutorial</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>7. Salutation</label>
                            <select name="salutation">
                                <option value="Selvan">Selvan</option>
                                <option value="Selvi">Selvi</option>
                                <option value="Thiru">Thiru</option>
                                <option value="Tmt">Tmt</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>8. Student Name (As per Certificate) <span class="req">*</span></label>
                            <input type="text" name="student_name_cert" id="umis_name_cert" placeholder="Name as printed in 12th Certificate">
                        </div>
                        <div class="form-group">
                            <label>9. Student Name (As per Aadhaar)</label>
                            <input type="text" name="student_name_aadhaar" id="umis_name_aadhaar" placeholder="Name as printed on Aadhaar Card">
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>10. Date of Birth <span class="req">*</span></label>
                            <input type="date" name="umis_dob" id="umis_dob">
                        </div>
                        <div class="form-group">
                            <label>11. Gender</label>
                            <select name="gender" id="umis_gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Third Gender">Third Gender</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>12. Blood Group</label>
                            <select name="blood_group" id="umis_blood_group">
                                <option value="">--Select--</option>
                                <option value="A+">A+</option><option value="A-">A-</option>
                                <option value="B+">B+</option><option value="B-">B-</option>
                                <option value="O+">O+</option><option value="O-">O-</option>
                                <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>13. Nationality</label>
                            <input type="text" name="nationality" id="umis_nationality" value="Indian">
                        </div>
                        <div class="form-group">
                            <label>14. Religion</label>
                            <select name="religion" id="umis_religion">
                                <option value="Hindu">Hindu</option>
                                <option value="Muslim">Muslim</option>
                                <option value="Christian">Christian</option>
                                <option value="Jain">Jain</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>15. Community</label>
                            <select name="umis_community" id="umis_community">
                                <option value="BC">BC</option>
                                <option value="MBC">MBC</option>
                                <option value="BCM">BCM</option>
                                <option value="SC">SC</option>
                                <option value="SCA">SCA</option>
                                <option value="ST">ST</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group">
                            <label>16. Caste</label>
                            <input type="text" name="umis_caste" id="umis_caste" placeholder="Caste Name">
                        </div>
                        <div class="form-group">
                            <label>17. Community Certificate Number</label>
                            <input type="text" name="community_cert_no" placeholder="e.g. CND/2023/12345">
                        </div>
                        <div class="form-group">
                            <label>18. Aadhaar Number</label>
                            <input type="text" name="umis_aadhaar_no" id="umis_aadhaar_no" maxlength="12" placeholder="12-digit Aadhaar Number">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>19. First Graduate?</label>
                            <select name="is_first_graduate" id="umis_first_graduate">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>19(a). First Graduate Certificate No</label>
                            <input type="text" name="first_graduate_cert_no" placeholder="e.g. FGC/2024/7890">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>20. Special Quota?</label>
                            <select name="special_quota">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>20(a). Special Quota Category</label>
                            <select name="special_quota_category">
                                <option value="">--None--</option>
                                <option value="Differently Abled">Differently Abled</option>
                                <option value="Sports">Sports</option>
                                <option value="Ex-Servicemen">Ex-Servicemen</option>
                                <option value="Andaman Nicobar">Andaman Nicobar</option>
                                <option value="NCC">NCC</option>
                                <option value="Defence">Defence</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>21. Differently Abled Category?</label>
                            <select name="is_differently_abled">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>21(a). UDID Number</label>
                            <input type="text" name="udid_no" placeholder="UDID / Disability Card No">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label>21(b). Disability Type</label>
                            <select name="disability_type">
                                <option value="">--None / Select--</option>
                                <option value="Blindness">Blindness</option>
                                <option value="Low Vision">Low Vision</option>
                                <option value="Leprosy Cured Persons">Leprosy Cured Persons</option>
                                <option value="Muscular Dystrophy">Muscular Dystrophy</option>
                                <option value="Chronic Neurological Conditions">Chronic Neurological Conditions</option>
                                <option value="Specific Learning Disabilities">Specific Learning Disabilities</option>
                                <option value="Hearing Impairment">Hearing Impairment</option>
                                <option value="Locomotor Disability">Locomotor Disability</option>
                                <option value="Acid Attack Victim">Acid Attack Victim</option>
                                <option value="Dwarfism">Dwarfism</option>
                                <option value="Intellectual Disability">Intellectual Disability</option>
                                <option value="Mental Illness">Mental Illness</option>
                                <option value="Autism Spectrum Disorder">Autism Spectrum Disorder</option>
                                <option value="Cerebral Palsy">Cerebral Palsy</option>
                                <option value="Multiple Sclerosis">Multiple Sclerosis</option>
                                <option value="Speech and Language Disability">Speech and Language Disability</option>
                                <option value="Thalassemia">Thalassemia</option>
                                <option value="Hemophilia">Hemophilia</option>
                                <option value="Sickle Cell Disease">Sickle Cell Disease</option>
                                <option value="Multiple Disabilities">Multiple Disabilities</option>
                                <option value="Parkinson's Disease">Parkinson's Disease</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>21(c). Extent (%) of Disability</label>
                            <input type="number" name="disability_percentage" placeholder="e.g. 40">
                        </div>
                    </div>
                </div>

                <!-- 3. CONTACT & ADDRESS DETAILS (ITEMS 22 - 43) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-map-location-dot"></i> 3. Contact & Address Details (Items 22 to 43)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>22. Mobile Number <span class="req">*</span></label>
                            <input type="tel" name="umis_mobile" id="umis_mobile" placeholder="10-digit mobile number">
                        </div>
                        <div class="form-group">
                            <label>23. Email ID <span class="req">*</span></label>
                            <input type="email" name="umis_email" id="umis_email" placeholder="student@aagacvpm.edu.in">
                        </div>
                    </div>

                    <h4 style="font-size:13px; font-weight:700; color:#1E3A8A; margin: 12px 0 8px 0;"><i class="fa-solid fa-house-chimney"></i> Permanent Native Address (Items 24 to 33)</h4>
                    <div class="grid-3">
                        <div class="form-group"><label>24. Country</label><input type="text" name="country" value="India"></div>
                        <div class="form-group"><label>25. State / UT</label><input type="text" name="state" value="Tamil Nadu"></div>
                        <div class="form-group">
                            <label>26. Location Type</label>
                            <select name="location_type">
                                <option value="Rural">Rural</option>
                                <option value="Urban">Urban</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group"><label>27. District</label><input type="text" name="district" placeholder="e.g. Villupuram"></div>
                        <div class="form-group"><label>28. Taluk</label><input type="text" name="taluk" placeholder="e.g. Vikravandi"></div>
                        <div class="form-group"><label>29. Village</label><input type="text" name="village" placeholder="Village Name"></div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group"><label>30. Block</label><input type="text" name="block" placeholder="e.g. Koliyanur"></div>
                        <div class="form-group"><label>31. Village Panchayat</label><input type="text" name="village_panchayat" placeholder="Panchayat Name"></div>
                        <div class="form-group"><label>32. Pincode</label><input type="text" name="pincode" placeholder="e.g. 605602"></div>
                    </div>
                    <div class="form-group">
                        <label>33. Postal Address</label>
                        <input type="text" name="postal_address" id="umis_postal_address" placeholder="Complete Door No, Street and Landmark">
                    </div>
                    
                    <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #CBD5E1;">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-weight:700; color:#1E3A8A; font-size:13px;">
                            <input type="checkbox" id="same_address_check" checked onchange="toggleCommAddress(this)" style="width:auto;">
                            Communication Address is the same as Permanent Native Address
                        </label>
                    </div>

                    <div id="comm_address_block" style="display:none; margin-top:14px; background:#F1F5F9; padding:14px; border-radius:10px; border:1px solid #CBD5E1;">
                        <h4 style="font-size:12.5px; font-weight:700; color:#334155; margin-bottom:10px;"><i class="fa-solid fa-envelope"></i> Communication Address (Items 34 to 43)</h4>
                        <div class="grid-3">
                            <div class="form-group"><label>34. Country</label><input type="text" name="comm_country" value="India"></div>
                            <div class="form-group"><label>35. State / UT</label><input type="text" name="comm_state" value="Tamil Nadu"></div>
                            <div class="form-group">
                                <label>36. Location Type</label>
                                <select name="comm_location_type">
                                    <option value="Rural">Rural</option>
                                    <option value="Urban">Urban</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid-3">
                            <div class="form-group"><label>37. District</label><input type="text" name="comm_district" placeholder="District"></div>
                            <div class="form-group"><label>38. Taluk</label><input type="text" name="comm_taluk" placeholder="Taluk"></div>
                            <div class="form-group"><label>39. Village</label><input type="text" name="comm_village" placeholder="Village"></div>
                        </div>
                        <div class="grid-3">
                            <div class="form-group"><label>40. Block</label><input type="text" name="comm_block" placeholder="Block"></div>
                            <div class="form-group"><label>41. Village Panchayat</label><input type="text" name="comm_village_panchayat" placeholder="Panchayat"></div>
                            <div class="form-group"><label>42. Pincode</label><input type="text" name="comm_pincode" placeholder="Pincode"></div>
                        </div>
                        <div class="form-group">
                            <label>43. Postal Address</label>
                            <input type="text" name="comm_postal_address" id="umis_comm_postal_address" placeholder="Door No, Street">
                        </div>
                    </div>
                </div>

                <!-- 4. FAMILY INFORMATION (ITEMS 44 - 52) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-people-roof"></i> 4. Family Information (Items 44 to 52)</div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>44. Is Orphan Category?</label>
                            <select name="is_orphan">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>50. Annual Family Income (in Rs.)</label>
                            <input type="number" name="family_income" id="umis_family_income" placeholder="e.g. 75000">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>45. Father's Name</label><input type="text" name="father_name" id="umis_father_name" placeholder="Father Name"></div>
                        <div class="form-group">
                            <label>46. Father's Occupation</label>
                            <select name="father_occupation" id="umis_father_occupation">
                                <option value="Daily Wage">Daily Wage</option>
                                <option value="Govt">Govt</option>
                                <option value="N.A">N.A</option>
                                <option value="Private">Private</option>
                                <option value="Self Employed" selected>Self Employed</option>
                                <option value="Unemployed">Unemployed</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group"><label>47. Mother's Name</label><input type="text" name="mother_name" id="umis_mother_name" placeholder="Mother Name"></div>
                        <div class="form-group">
                            <label>48. Mother's Occupation</label>
                            <select name="mother_occupation">
                                <option value="Daily Wage" selected>Daily Wage</option>
                                <option value="Govt">Govt</option>
                                <option value="N.A">N.A</option>
                                <option value="Private">Private</option>
                                <option value="Self Employed">Self Employed</option>
                                <option value="Unemployed">Unemployed</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-3">
                        <div class="form-group"><label>49. Guardian / Spouse Name</label><input type="text" name="guardian_name" id="umis_guardian_name" placeholder="If applicable"></div>
                        <div class="form-group"><label>51. Income Cert Number</label><input type="text" name="income_cert_no" placeholder="e.g. INC/2024/4567"></div>
                        <div class="form-group"><label>52. Parent / Guardian Mobile</label><input type="tel" name="guardian_mobile" id="umis_guardian_mobile" placeholder="Guardian Phone"></div>
                    </div>
                </div>

                <!-- 5. BANK INFORMATION - AADHAAR SEEDING & ACCOUNT (ITEMS 53 - 63) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-money-check-dollar"></i> 5. Bank Information & Aadhaar Seeding (Items 53 to 63)</div>
                    <h4 style="font-size:13px; font-weight:700; color:#1E3A8A; margin-bottom:8px;"><i class="fa-solid fa-shield-halved"></i> Bank Information - Aadhaar Seeding (Items 53 to 57)</h4>
                    <div class="grid-2">
                        <div class="form-group"><label>53. Account Number (Aadhaar Seeded)</label><input type="text" name="seed_account_no" id="umis_seed_account_no" placeholder="Account linked to Aadhaar"></div>
                        <div class="form-group"><label>54. Mobile Number (Aadhaar Seeded)</label><input type="text" name="seed_mobile" id="umis_seed_mobile" placeholder="Mobile linked to Bank"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>55. Bank Name</label><input type="text" name="seed_bank_name" id="umis_seed_bank_name" placeholder="e.g. State Bank of India"></div>
                        <div class="form-group">
                            <label>56. Status</label>
                            <select name="seed_status">
                                <option value="Active">Active</option>
                                <option value="Not Active">Not Active</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>57. Message</label>
                        <input type="text" name="seed_message" value="Your Bank Account – Aadhaar Seeding has been done">
                    </div>

                    <h4 style="font-size:13px; font-weight:700; color:#1E3A8A; margin:16px 0 8px 0;"><i class="fa-solid fa-building-columns"></i> Account Active Status (Items 58 to 63)</h4>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>58. Account Number <span class="req">*</span></label>
                            <input type="text" name="acc_no" id="umis_acc_no" required placeholder="Bank Account Number" oninput="syncBankToSeed()">
                        </div>
                        <div class="form-group">
                            <label>59. IFSC Code <span class="req">*</span></label>
                            <input type="text" name="ifsc_code" style="text-transform: uppercase;" required placeholder="e.g. SBIN0001234">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>60. Bank Name</label><input type="text" name="bank_name" id="umis_bank_name" placeholder="e.g. State Bank of India" oninput="syncBankToSeed()"></div>
                        <div class="form-group"><label>61. Bank Branch</label><input type="text" name="bank_branch" placeholder="e.g. Villupuram Main"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label>62. City</label><input type="text" name="bank_city" value="Villupuram" placeholder="e.g. Villupuram"></div>
                        <div class="form-group">
                            <label>63. Account Type</label>
                            <select name="account_type">
                                <option value="SB">SB</option>
                                <option value="CA">CA</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 6. ACADEMIC & ADMISSION INFORMATION (ITEMS 64 - 79) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-book-bookmark"></i> 6. Academic Information (Items 64 to 79)</div>
                    <div class="grid-3">
                        <div class="form-group"><label>64. Joining Year</label><input type="text" name="join_year" value="2025–2028"></div>
                        <div class="form-group"><label>65. Stream Type</label><input type="text" name="stream_type" value="Regular"></div>
                        <div class="form-group"><label>66. Course Type</label><input type="text" name="course_type" value="Regular"></div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>67. Course / Department</label>
                            <input type="text" name="umis_course_display" id="umis_course_display" readonly style="background:#F8FAFC;" value="CS - Computer Science">
                        </div>
                        <div class="form-group"><label>68. Branch / Specialization</label><input type="text" name="specialization" id="umis_specialization" placeholder="e.g. Computer Science"></div>
                        <div class="form-group">
                            <label>69. Medium of Instruction</label>
                            <select name="medium_of_instruction" id="umis_medium">
                                <option value="English">English</option>
                                <option value="Tamil">Tamil</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>70. Mode of Study</label>
                            <select name="mode_of_study">
                                <option value="Regular" selected>Regular</option>
                                <option value="Distance">Distance</option>
                                <option value="Part-time">Part-time</option>
                                <option value="Week-End">Week-End</option>
                            </select>
                        </div>
                        <div class="form-group"><label>71. Date of Admission</label><input type="date" name="date_of_admission" id="umis_adm_date"></div>
                        <div class="form-group"><label>72. Type of Admission</label><input type="text" name="type_of_admission" value="Single Window"></div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group"><label>73. Counselling / Admission No</label><input type="text" name="counselling_no" placeholder="Allotment / Counselling Order No"></div>
                        <div class="form-group"><label>74. Roll Number</label><input type="text" name="umis_roll_no" id="umis_roll_no" readonly style="background:#F8FAFC;" placeholder="Roll Number"></div>
                        <div class="form-group">
                            <label>75. Lateral Entry?</label>
                            <select name="is_lateral_entry">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-3">
                        <div class="form-group">
                            <label>76. Hosteller?</label>
                            <select name="is_hosteller" id="umis_is_hosteller">
                                <option value="No">No</option>
                                <option value="Yes">Yes</option>
                            </select>
                        </div>
                        <div class="form-group"><label>77. Current Status</label><input type="text" name="current_status" value="Studying in this Institute"></div>
                        <div class="form-group"><label>78. Course Completed Year</label><input type="text" name="year_of_course_completed" placeholder="e.g. 2027"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>79. Year of Study & Degree Level</label>
                            <select name="year_of_study" id="umis_year_of_study">
                                <option value="1st Year">UG - 1st Year (I Year)</option>
                                <option value="2nd Year">UG - 2nd Year (II Year)</option>
                                <option value="3rd Year">UG - 3rd Year (III Year)</option>
                                <option value="1st Year PG">PG - 1st Year (I PG)</option>
                                <option value="2nd Year PG">PG - 2nd Year (II PG)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Parent Phone Number</label><input type="tel" name="umis_parent_phone" id="umis_parent_phone" placeholder="Parent Mobile"></div>
                    </div>
                </div>

                <!-- 7. SCHOOL OF STUDY INFORMATION (ITEM 80) -->
                <div class="sub-section">
                    <div class="sub-section-title"><i class="fa-solid fa-school"></i> 7. School of Study Information (Item 80: 6th to 12th Std)</div>
                    <div style="overflow-x: auto;">
                        <table class="school-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Class</th>
                                    <th style="width: 45%;">School Name as per Records</th>
                                    <th style="width: 20%;">District</th>
                                    <th style="width: 20%;">Type of School</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $classes_arr = [
                                    '12th' => '12th Std (HSC)',
                                    '11th' => '11th Std',
                                    '10th' => '10th Std (SSLC)',
                                    '9th'  => '9th Std',
                                    '8th'  => '8th Std',
                                    '7th'  => '7th Std',
                                    '6th'  => '6th Std'
                                ];
                                foreach ($classes_arr as $c_code => $c_label): ?>
                                <tr>
                                    <td><strong><?=$c_label?></strong></td>
                                    <td><input type="text" name="sch_name_<?=$c_code?>" placeholder="School Name"></td>
                                    <td><input type="text" name="sch_dist_<?=$c_code?>" placeholder="District"></td>
                                    <td>
                                        <select name="sch_type_<?=$c_code?>">
                                            <option value="Govt">Government (Govt)</option>
                                            <option value="Aided">Govt. Aided</option>
                                            <option value="Unaided">Private / Unaided</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-prev" onclick="switchStep(2)">
                        <i class="fa-solid fa-arrow-left"></i> Back: Bio Data
                    </button>
                    <button type="submit" class="btn btn-submit">
                        <i class="fa-solid fa-paper-plane"></i> Submit All Details (Students, Bio Data & UMIS)
                    </button>
                </div>
            </div>

        </form>
    </div>
</div>

<script>
let currentStep = 1;

function switchStep(step) {
    document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.step-section').forEach(el => el.classList.remove('active'));

    document.getElementById(`stepTab${step}`).classList.add('active');
    document.getElementById(`stepSection${step}`).classList.add('active');

    // Mark previous steps as done
    for (let i = 1; i < step; i++) {
        document.getElementById(`stepTab${i}`).classList.add('done');
    }

    currentStep = step;
    window.scrollTo({ top: 120, behavior: 'smooth' });
}

function validateAndGoStep(targetStep) {
    if (targetStep === 1) {
        switchStep(1);
        return;
    }
    if (currentStep === 1 || targetStep === 2) {
        const photoInput = document.getElementById('field_student_photo_file');
        const name = document.getElementById('field_name')?.value.trim() || '';
        const regNo = document.getElementById('field_exam_reg_no')?.value.trim() || '';
        const rollNo = document.getElementById('field_roll_no')?.value.trim() || '';
        const admNo = document.getElementById('field_admission_no')?.value.trim() || '';
        const email = document.getElementById('field_email')?.value.trim() || '';

        if (!photoInput || !photoInput.files || photoInput.files.length === 0) {
            alert("Please select and upload a Student Passport Size Photo!");
            if (photoInput) photoInput.focus();
            return;
        }
        if (!name) { alert("Please enter Student Full Name!"); document.getElementById('field_name')?.focus(); return; }
        if (!regNo) { alert("Please enter Examination Register Number!"); document.getElementById('field_exam_reg_no')?.focus(); return; }
        if (!rollNo) { alert("Please enter Roll Number!"); document.getElementById('field_roll_no')?.focus(); return; }
        if (!admNo) { alert("Please enter Admission Number!"); document.getElementById('field_admission_no')?.focus(); return; }
        if (!email || !email.includes('@')) { alert("Please enter a valid Student Email address!"); document.getElementById('field_email')?.focus(); return; }
    }

    syncSharedFields();
    switchStep(targetStep);
}

// Automatically sync common fields across all 3 steps
function syncSharedFields() {
    const name = document.getElementById('field_name')?.value.trim() || '';
    const regNo = document.getElementById('field_exam_reg_no')?.value.trim() || '';
    const rollNo = document.getElementById('field_roll_no')?.value.trim() || '';
    const email = document.getElementById('field_email')?.value.trim() || '';
    const phone = document.getElementById('field_student_phone')?.value.trim() || '';
    const parentPhone = document.getElementById('field_parent_phone')?.value.trim() || '';
    const parentName = document.getElementById('field_parent_name')?.value.trim() || '';
    const parentOcc = document.getElementById('field_parent_occupation')?.value.trim() || '';
    const permAddr = document.getElementById('field_permanent_address')?.value.trim() || '';
    const contactAddr = document.getElementById('field_parent_address')?.value.trim() || permAddr;
    const dob = document.getElementById('field_dob')?.value || '';
    const gender = document.getElementById('field_gender')?.value || '';
    const comm = document.getElementById('field_community')?.value || '';
    const dateJoining = document.getElementById('field_date_joining')?.value || '';
    const mainSubj = document.getElementById('field_main_subject')?.value.trim() || '';
    const medium = document.getElementById('field_medium')?.value || 'English';
    const courseEl = document.getElementById('field_course');
    const yLevel = document.getElementById('field_year_level')?.value || '';

    // Set hidden reg_no
    const hiddenReg = document.getElementById('field_reg_no');
    if (hiddenReg) hiddenReg.value = regNo;

    // Bio Data Sync (All 19 Fields Sync)
    const bioName = document.getElementById('bio_name_ta_en');
    if (bioName && !bioName.value) bioName.value = name;

    const bioTamilReg = document.getElementById('bio_tamil_reg_no');
    if (bioTamilReg && !bioTamilReg.value) bioTamilReg.value = regNo;

    const bioDob = document.getElementById('bio_dob');
    if (bioDob && (!bioDob.value || bioDob.value === dob)) bioDob.value = dob;

    const bioComm = document.getElementById('bio_community');
    if (bioComm && comm) {
        for (let opt of bioComm.options) {
            if (opt.value === comm) { opt.selected = true; break; }
        }
    }

    const bioOcc = document.getElementById('bio_parent_occupation');
    if (bioOcc && (!bioOcc.value || bioOcc.value === parentOcc)) bioOcc.value = parentOcc;

    const bioContact = document.getElementById('bio_contact_address');
    if (bioContact && !bioContact.value) bioContact.value = contactAddr;

    const bioPerm = document.getElementById('bio_permanent_address');
    if (bioPerm && (!bioPerm.value || bioPerm.value === permAddr)) bioPerm.value = permAddr;

    const bioPhone = document.getElementById('bio_student_phone');
    if (bioPhone && (!bioPhone.value || bioPhone.value === phone)) bioPhone.value = phone;

    const bioParentPhone = document.getElementById('bio_parent_phone');
    if (bioParentPhone && (!bioParentPhone.value || bioParentPhone.value === parentPhone)) bioParentPhone.value = parentPhone;

    // UMIS Sync
    const bioEmis = document.getElementById('bio_emis_no')?.value || '';
    const umisEmis = document.getElementById('umis_emis_id');
    if (umisEmis && (!umisEmis.value || umisEmis.value === bioEmis)) umisEmis.value = bioEmis;

    const umisCert = document.getElementById('umis_name_cert');
    if (umisCert && !umisCert.value) umisCert.value = name;

    const umisAadhaar = document.getElementById('umis_name_aadhaar');
    if (umisAadhaar && !umisAadhaar.value) umisAadhaar.value = name;

    const umisDob = document.getElementById('umis_dob');
    if (umisDob && (!umisDob.value || umisDob.value === dob)) umisDob.value = dob;

    const umisGender = document.getElementById('umis_gender');
    if (umisGender && gender) umisGender.value = gender;

    const bioBlood = document.getElementById('bio_blood_group')?.value || '';
    const umisBlood = document.getElementById('umis_blood_group');
    if (umisBlood && bioBlood) umisBlood.value = bioBlood;

    const bioNat = document.getElementById('bio_nationality')?.value || '';
    const umisNat = document.getElementById('umis_nationality');
    if (umisNat && bioNat) umisNat.value = bioNat;

    const bioRel = document.getElementById('bio_religion')?.value || '';
    const umisRel = document.getElementById('umis_religion');
    if (umisRel && bioRel) umisRel.value = bioRel;

    const umisComm = document.getElementById('umis_community');
    if (umisComm && comm) {
        for (let opt of umisComm.options) {
            if (opt.value === comm) { opt.selected = true; break; }
        }
    }

    const bioCaste = document.getElementById('bio_caste')?.value || '';
    const umisCaste = document.getElementById('umis_caste');
    if (umisCaste && (!umisCaste.value || umisCaste.value === bioCaste)) umisCaste.value = bioCaste;

    const bioAadhaar = document.getElementById('bio_aadhaar_no')?.value || '';
    const umisAadhaarNo = document.getElementById('umis_aadhaar_no');
    if (umisAadhaarNo && (!umisAadhaarNo.value || umisAadhaarNo.value === bioAadhaar)) umisAadhaarNo.value = bioAadhaar;

    const umisMob = document.getElementById('umis_mobile');
    if (umisMob && !umisMob.value) umisMob.value = phone;

    const umisEmail = document.getElementById('umis_email');
    if (umisEmail && !umisEmail.value) umisEmail.value = email;

    const umisFather = document.getElementById('umis_father_name');
    if (umisFather && !umisFather.value) umisFather.value = parentName;

    const umisFatherOcc = document.getElementById('umis_father_occupation');
    if (umisFatherOcc && parentOcc) {
        for (let opt of umisFatherOcc.options) {
            if (opt.value.toLowerCase() === parentOcc.toLowerCase()) {
                opt.selected = true; break;
            }
        }
    }

    const bioMother = document.getElementById('bio_mother_name')?.value || '';
    const umisMother = document.getElementById('umis_mother_name');
    if (umisMother && !umisMother.value) umisMother.value = bioMother;

    const bioMotherOcc = document.getElementById('bio_mother_occupation')?.value || '';
    const umisMotherOcc = document.querySelector('select[name="mother_occupation"]');
    if (umisMotherOcc && bioMotherOcc) {
        for (let opt of umisMotherOcc.options) {
            if (opt.value.toLowerCase() === bioMotherOcc.toLowerCase()) {
                opt.selected = true; break;
            }
        }
    }

    const bioIncome = document.getElementById('bio_parent_income')?.value || '';
    const umisIncome = document.getElementById('umis_family_income');
    if (umisIncome && (!umisIncome.value || umisIncome.value === bioIncome)) umisIncome.value = bioIncome;

    const umisGuardPhone = document.getElementById('umis_guardian_mobile');
    if (umisGuardPhone && !umisGuardPhone.value) umisGuardPhone.value = parentPhone;

    const umisPostal = document.getElementById('umis_postal_address');
    if (umisPostal && !umisPostal.value) umisPostal.value = permAddr;

    const umisCourseDisp = document.getElementById('umis_course_display');
    if (umisCourseDisp && courseEl) {
        umisCourseDisp.value = courseEl.options[courseEl.selectedIndex]?.text || courseEl.value;
    }

    const umisRoll = document.getElementById('umis_roll_no');
    if (umisRoll) umisRoll.value = rollNo;

    const umisAdmDate = document.getElementById('umis_adm_date');
    if (umisAdmDate && !umisAdmDate.value) umisAdmDate.value = dateJoining;

    const umisSeedMob = document.getElementById('umis_seed_mobile');
    if (umisSeedMob && !umisSeedMob.value) umisSeedMob.value = phone;

    const umisSpec = document.getElementById('umis_specialization');
    if (umisSpec && !umisSpec.value) umisSpec.value = mainSubj;

    const umisMed = document.getElementById('umis_medium');
    if (umisMed && medium) umisMed.value = medium;

    const umisParentPhone = document.getElementById('umis_parent_phone');
    if (umisParentPhone && !umisParentPhone.value) umisParentPhone.value = parentPhone;

    const accom = document.getElementById('bio_accommodation')?.value || '';
    const umisHostel = document.getElementById('umis_is_hosteller');
    if (umisHostel && accom) {
        umisHostel.value = (accom.indexOf('Hostel') !== -1 || accom === 'Room') ? 'Yes' : 'No';
    }

    const umisYOS = document.getElementById('umis_year_of_study');
    if (umisYOS && yLevel) {
        if (yLevel === 'UG_1') umisYOS.value = '1st Year';
        else if (yLevel === 'UG_2') umisYOS.value = '2nd Year';
        else if (yLevel === 'UG_3') umisYOS.value = '3rd Year';
        else if (yLevel === 'PG_1') umisYOS.value = '1st Year PG';
        else if (yLevel === 'PG_2') umisYOS.value = '2nd Year PG';
    }
}

function syncBankToSeed() {
    const acc = document.getElementById('umis_acc_no')?.value.trim() || '';
    const bank = document.getElementById('umis_bank_name')?.value.trim() || '';

    const seedAcc = document.getElementById('umis_seed_account_no');
    if (seedAcc && (!seedAcc.value || seedAcc.value === acc.slice(0, -1))) {
        seedAcc.value = acc;
    }

    const seedBank = document.getElementById('umis_seed_bank_name');
    if (seedBank && (!seedBank.value || seedBank.value === bank.slice(0, -1))) {
        seedBank.value = bank;
    }
}

function syncBioDobToMain(val) {
    const el = document.getElementById('field_dob');
    if (el) el.value = val;
    syncSharedFields();
}

function syncBioCommunityToMain(val) {
    const el = document.getElementById('field_community');
    if (el) el.value = val;
    syncSharedFields();
}

function syncBioOccToMain(val) {
    const el = document.getElementById('field_parent_occupation');
    if (el) el.value = val;
    syncSharedFields();
}

function syncBioPermAddrToMain(val) {
    const el = document.getElementById('field_permanent_address');
    if (el) el.value = val;
    syncSharedFields();
}

function syncBioPhoneToMain(val) {
    const el = document.getElementById('field_student_phone');
    if (el) el.value = val;
    syncSharedFields();
}

function syncBioParentPhoneToMain(val) {
    const el = document.getElementById('field_parent_phone');
    if (el) el.value = val;
    syncSharedFields();
}

function previewFile(input, targetImgId) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 8 * 1024 * 1024) {
            alert("File size exceeds 8MB! Please select a smaller photo.");
            input.value = "";
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById(targetImgId);
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Dynamic Marks Table Functions
function addSubjectRow(sem, subject = '', ue = '', ia = '', total = '', pf = '') {
    const tbody = document.getElementById(`sem${sem}_body`);
    if (!tbody) return;
    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td style="text-align:left;"><input type="text" name="sem${sem}_subject[]" value="${escapeHtml(subject)}" placeholder="Subject Name"></td>
        <td><input type="number" name="sem${sem}_ue[]" value="${ue}" placeholder="0" min="0" max="75" oninput="calculateMarks(this)"></td>
        <td><input type="number" name="sem${sem}_ia[]" value="${ia}" placeholder="0" min="0" max="25" oninput="calculateMarks(this)"></td>
        <td><input type="number" name="sem${sem}_total[]" value="${total}" readonly style="background:#F8FAFC; font-weight:700;"></td>
        <td><input type="text" name="sem${sem}_pf[]" value="${pf}" readonly class="${pf === 'Pass' ? 'pf-pass' : (pf === 'Fail' ? 'pf-fail' : '')}" style="background:#F8FAFC; font-weight:700;"></td>
        <td><button type="button" class="btn-delete" title="Delete Row" onclick="removeRow(this)">🗑️</button></td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const row = btn.closest('tr');
    if (row) row.remove();
}

function calculateMarks(input) {
    const row = input.closest('tr');
    if (!row) return;
    const ueInput = row.querySelector('input[name*="_ue"]');
    const iaInput = row.querySelector('input[name*="_ia"]');
    const totalInput = row.querySelector('input[name*="_total"]');
    const pfInput = row.querySelector('input[name*="_pf"]');

    if (!ueInput || !iaInput || !totalInput || !pfInput) return;

    const rawUE = ueInput.value.trim();
    const rawIA = iaInput.value.trim();

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

function switchSemTab(sem) {
    for (let i = 1; i <= 6; i++) {
        const pane = document.getElementById(`semPane_${i}`);
        const btn = document.getElementById(`semTabBtn_${i}`);
        if (pane) pane.style.display = (i === sem) ? 'block' : 'none';
        if (btn) {
            if (i === sem) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    }
}

function onSemesterChange(val) {
    const match = val.match(/\d+/);
    if (match) {
        const semNum = parseInt(match[0], 10);
        if (semNum >= 1 && semNum <= 6) {
            switchSemTab(semNum);
        }
    }
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function toggleCommAddress(chk) {
    const block = document.getElementById('comm_address_block');
    if (block) block.style.display = chk.checked ? 'none' : 'block';
}

function syncAcademicClass() {
    const dept = document.getElementById('field_course').value;
    const yearLevel = document.getElementById('field_year_level').value;

    const artsDepts = ['TAM', 'ENG', 'HIST', 'ECO'];
    const isArts = artsDepts.includes(dept);
    const isComm = (dept === 'COMM');
    const isBCA  = (dept === 'BCA');

    let degPrefix = 'B.Sc';
    if (yearLevel.startsWith('PG')) {
        if (isArts) degPrefix = 'M.A';
        else if (isComm) degPrefix = 'M.Com';
        else degPrefix = 'M.Sc';
    } else {
        if (isArts) degPrefix = 'B.A';
        else if (isComm) degPrefix = 'B.Com';
        else if (isBCA)  degPrefix = 'BCA';
        else degPrefix = 'B.Sc';
    }

    let yearNum = 'I';
    if (yearLevel === 'UG_2' || yearLevel === 'PG_2') yearNum = 'II';
    else if (yearLevel === 'UG_3') yearNum = 'III';

    const shortClass = `${yearNum} ${degPrefix}`;

    const deptNames = {
        'TAM': 'Tamil', 'ENG': 'English', 'HIST': 'History', 'ECO': 'Economics', 'COMM': 'Commerce',
        'MATH': 'Mathematics', 'PHY': 'Physics', 'CHEM': 'Chemistry', 'BOT': 'Botany', 'ZOO': 'Zoology',
        'STAT': 'Statistics', 'CS': 'Computer Science', 'BCA': 'Computer Applications', 'IT': 'Information Technology'
    };

    const majorTitle = `${shortClass} ${deptNames[dept] || dept}`;
    const mainSubj = document.getElementById('field_main_subject');
    if (mainSubj && (!mainSubj.value || mainSubj.value.includes('B.Sc') || mainSubj.value.includes('B.A') || mainSubj.value.includes('B.Com'))) {
        mainSubj.value = majorTitle;
    }

    const bioClass = document.getElementById('bio_class_input');
    if (bioClass) bioClass.value = shortClass;

    const umisSpec = document.getElementById('umis_specialization');
    if (umisSpec && !umisSpec.value) umisSpec.value = majorTitle;
}

// Run initial setup on load
document.addEventListener('DOMContentLoaded', () => {
    syncAcademicClass();

    // Check initial semester dropdown value and activate matching tab
    const curSem = document.getElementById('field_current_semester');
    if (curSem && curSem.value) {
        onSemesterChange(curSem.value);
    }
});
</script>
</body>
</html>
