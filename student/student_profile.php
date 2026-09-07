<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure only Students can access this page
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

$display_role = $_SESSION['role'];
$student_dashboard_link = "dashboard_student.php";

// Safe Multi-location .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/../.env',                  
    __DIR__ . '/.env',                     
    $_SERVER['DOCUMENT_ROOT'] . '/SRMS/.env'
];

foreach ($possible_env_paths as $path) {
    if (file_exists($path)) {
        $parsed = @parse_ini_file($path);
        if ($parsed) {
            $env = $parsed;
            break;
        }
    }
}

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');

$student_id = $_SESSION['student_id'] ?? ($_SESSION['user_id'] ?? '');
$success_msg = "";
$error_msg = "";

// Fetch Current Student Profile Data Safely
$data = [];
if ($student_id && !empty($SUPABASE_URL)) {
    $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($student_id);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $SUPABASE_KEY", "Authorization: Bearer $SUPABASE_KEY"]);
    $res = curl_exec($ch); 
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($res, true);
    if ($http_code >= 200 && $http_code < 300) {
        if (is_array($decoded) && count($decoded) > 0 && isset($decoded[0])) {
            $data = $decoded[0];
        } else {
            $error_msg = "No student record found in database for ID: " . htmlspecialchars($student_id);
        }
    } else {
        $error_msg = "Supabase Fetch Error: " . htmlspecialchars($res);
    }
}

// Handle Form Submissions & File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
    if (isset($_POST['update_profile'])) {
        $name_ta_en = trim($_POST['name_ta_en'] ?? '');
        $tamil_reg_no = trim($_POST['tamil_reg_no'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $cgpa = trim($_POST['cgpa'] ?? '');
        $prev_attendance = trim($_POST['prev_attendance'] ?? '');

      // Handle File Upload for Profile Photo to Supabase Storage
$photo_path = isset($data['student_photo']) ? $data['student_photo'] : '';

if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $file_tmp  = $_FILES['profile_photo']['tmp_name'];
    $file_name = $_FILES['profile_photo']['name'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($file_ext, $allowed_exts)) {

        $user_id_filename = $_SESSION['user_id'] ?? time();
        $new_filename = "student_" . $user_id_filename . "_" . time() . "." . $file_ext;
        $bucket_name = "student-photos";

        // Endpoint
        $upload_url = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/" . $bucket_name . "/" . $new_filename;

        $file_data = file_get_contents($file_tmp);
        $mime_type = mime_content_type($file_tmp);

        $ch = curl_init($upload_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: " . $SUPABASE_KEY,
            "Authorization: Bearer " . $SUPABASE_KEY,
            "Content-Type: " . $mime_type,
            "x-upsert: true"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $photo_path = rtrim($SUPABASE_URL, '/') . "/storage/v1/object/public/" . $bucket_name . "/" . $new_filename;
        } else {
            // Detailed alert for debugging
            $err_data = json_decode($response, true);
            $msg = $err_data['message'] ?? 'Status Code: ' . $httpCode;
            echo "<script>alert('Supabase Upload Error: " . addslashes($msg) . "');</script>";
        }
    }
}

        $update_data = json_encode([
            'name_ta_en' => $name_ta_en,
            'tamil_reg_no' => $tamil_reg_no,
            'email' => $email,
            'student_phone' => $phone,
            'dob' => $dob,
            'contact_address' => $address,
            'department' => $department,
            'class' => $class,
            'semester' => $semester,
            'cgpa' => $cgpa,
            'prev_attendance' => $prev_attendance,
            'student_photo' => $photo_path
        ]);

        $ch = curl_init(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($student_id));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ]);
        $response = curl_exec($ch);
        $update_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($update_http_code >= 200 && $update_http_code < 300) {
            $success_msg = "Profile updated successfully!";
            $data['name_ta_en'] = $name_ta_en;
            $data['tamil_reg_no'] = $tamil_reg_no;
            $data['email'] = $email;
            $data['student_phone'] = $phone;
            $data['dob'] = $dob;
            $data['contact_address'] = $address;
            $data['department'] = $department;
            $data['class'] = $class;
            $data['semester'] = $semester;
            $data['cgpa'] = $cgpa;
            $data['prev_attendance'] = $prev_attendance;
            $data['student_photo'] = $photo_path;
        } else {
            $error_msg = "Failed to update profile. (HTTP $update_http_code) Details: " . htmlspecialchars($response ?: $curl_error);
        }
    }
}

function show($val, $default = ''){ 
    return isset($val) ? htmlspecialchars((string)$val) : $default; 
}
$user_name = $data['name_ta_en'] ?? ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Profile - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #1E293B;
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 260px;
    background: #0B132B;
    color: #FFFFFF;
    display: flex;
    flex-direction: column;
    position: fixed;
    height: 100vh;
    z-index: 100;
    justify-content: space-between;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}
.sidebar-content { overflow-y: auto; flex: 1; }
.sidebar-header {
    padding: 24px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.sidebar-header .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #059669 0%, #10B981 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #FFFFFF;
    box-shadow: 0 4px 10px rgba(5, 150, 105, 0.25);
}
.sidebar-header h2 { font-size: 16px; font-weight: 800; color: #FFFFFF; letter-spacing: -0.2px; }
.sidebar-header span { font-size: 11px; color: #94A3B8; font-weight: 500; display: block; }
.sidebar-menu { list-style: none; padding: 16px 12px; }
.sidebar-menu li { margin-bottom: 3px; }
.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    color: #94A3B8;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}
.sidebar-menu a:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #FFFFFF;
}
.sidebar-menu a.active {
    background: #059669;
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(5, 150, 105, 0.35);
}
.sidebar-menu a i { width: 18px; text-align: center; font-size: 14px; }
.nav-category {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #64748B;
    padding: 14px 14px 6px;
    font-weight: 700;
}
.sidebar-footer {
    padding: 16px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    transition: 0.2s;
}
.sidebar-footer a:hover { background: rgba(255, 255, 255, 0.06); }
.sidebar-footer a.active {
    background: #059669;
    color: #FFFFFF;
    font-weight: 600;
}

/* Main Content Area */
.main-content {
    margin-left: 260px;
    width: calc(100% - 260px);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Topbar */
.topbar {
    background: #FFFFFF;
    border-bottom: 1px solid #E2E8F0;
    padding: 16px 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 90;
}
.topbar-title h1 { font-size: 20px; font-weight: 800; color: #0F172A; }
.topbar-title p { font-size: 12.5px; color: #64748B; margin-top: 2px; }
.user-profile { display: flex; align-items: center; gap: 12px; }
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.3px;
    text-transform: uppercase;
}

.content-body {
    padding: 32px 36px;
    width: 100%;
}

/* Profile Hero Header Card */
.profile-hero-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 26px 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 20px;
}
.profile-hero-left {
    display: flex;
    align-items: center;
    gap: 20px;
}
.avatar-container {
    position: relative;
    width: 96px;
    height: 96px;
}
.avatar-img {
    width: 96px;
    height: 96px;
    object-fit: cover;
    border-radius: 16px;
    border: 3px solid #ECFDF5;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.15);
}
.avatar-badge {
    position: absolute;
    bottom: -4px;
    right: -4px;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #059669;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border: 2px solid white;
}
.hero-info h2 {
    font-size: 21px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 4px;
}
.hero-info p {
    font-size: 13.5px;
    color: #64748B;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.hero-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    background: #F1F5F9;
    color: #334155;
}
.hero-tag.emerald {
    background: #ECFDF5;
    color: #059669;
    border: 1px solid #A7F3D0;
}

/* Alert Boxes */
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 13.5px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    word-break: break-all;
}

/* Form Container */
.profile-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 16px;
    padding: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
}

.section-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 28px;
}

.section-box {
    background: #FAFBFC;
    border: 1px solid #E2E8F0;
    border-radius: 14px;
    padding: 22px;
}

.section-box h3 {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #059669;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #E2E8F0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 800;
}

/* Photo Upload Box */
.photo-upload-box {
    background: #F8FAFC;
    border: 2px dashed #CBD5E1;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: 0.2s;
}
.photo-upload-box:hover {
    border-color: #059669;
    background: #F0FDF4;
}
.photo-upload-info h4 {
    font-size: 13.5px;
    font-weight: 700;
    color: #0F172A;
    margin-bottom: 3px;
}
.photo-upload-info p {
    font-size: 11.5px;
    color: #64748B;
}

/* Form Fields */
.form-group {
    margin-bottom: 14px;
}
.form-group label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.form-group input, .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    font-size: 13.5px;
    font-family: inherit;
    outline: none;
    background: #FFFFFF;
    transition: all 0.2s ease;
    color: #1E293B;
}
.form-group input:focus, .form-group textarea:focus {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12);
}
.form-group input[type="file"] {
    padding: 8px 12px;
    background: #FFFFFF;
    cursor: pointer;
    font-size: 12.5px;
}

/* Submit Button */
.btn-save {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 10px;
    cursor: pointer;
    border: none;
    background: #059669;
    color: white;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.2);
}
.btn-save:hover {
    background: #047857;
    box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
    transform: translateY(-1px);
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
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
            <li><a href="download_certificates.php"><i class="fa-solid fa-file-arrow-down"></i> <span>Download Certificates</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="student_profile.php" class="active" style="color:#FFFFFF;">
            <i class="fa-solid fa-id-badge" style="width:18px; text-align:center;"></i> <span>My Profile</span>
        </a>
        <a href="../auth/logout.php" style="color:#F87171;">
            <i class="fa-solid fa-right-from-bracket" style="width:18px; text-align:center;"></i> <span>Logout</span>
        </a>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-title">
            <h1>Student Profile & Settings</h1>
            <p>Manage personal credentials, contact information, and academic records</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-graduation-cap"></i> Student Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($user_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">
        
        <?php if($success_msg): ?>
            <div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- HERO PROFILE HEADER -->
        <div class="profile-hero-card">
            <div class="profile-hero-left">
                <div class="avatar-container">
                    <?php if(!empty($data['student_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($data['student_photo']); ?>" alt="Profile Photo" id="photoPreview" class="avatar-img">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/96?text=Student" alt="Profile Photo" id="photoPreview" class="avatar-img">
                    <?php endif; ?>
                    <div class="avatar-badge">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                </div>
                <div class="hero-info">
                    <h2><?php echo show($data['name_ta_en'] ?? $user_name, 'Student User'); ?></h2>
                    <p>
                        <span><i class="fa-solid fa-id-card"></i> Roll No: <strong><?php echo show($data['tamil_reg_no'] ?? 'N/A'); ?></strong></span>
                        <span class="hero-tag emerald"><i class="fa-solid fa-circle-check"></i> Active Student</span>
                        <span class="hero-tag"><i class="fa-solid fa-building-columns"></i> <?php echo show($data['department'] ?? 'Computer Science'); ?></span>
                    </p>
                </div>
            </div>
        </div>

        <!-- PROFILE FORM -->
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="profile-card">
                
                <!-- Photo Upload Section -->
                <div class="photo-upload-box">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 28px; color: #059669;"></i>
                    <div class="photo-upload-info" style="flex: 1;">
                        <h4>Change Profile Photograph</h4>
                        <p>Upload a clear passport-sized photo (JPG, JPEG, PNG, WEBP)</p>
                    </div>
                    <div style="max-width: 280px; width: 100%;">
                        <input type="file" name="profile_photo" accept="image/*" onchange="previewImage(event)">
                    </div>
                </div>

                <div class="section-grid">
                    
                    <!-- 1. BASIC SECTION (All Editable) -->
                    <div class="section-box">
                        <h3><i class="fa-solid fa-user"></i> Personal Details</h3>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name_ta_en" value="<?php echo show($data['name_ta_en'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Registration / Roll No</label>
                            <input type="text" name="tamil_reg_no" value="<?php echo show($data['tamil_reg_no'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo show($data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" value="<?php echo show($data['student_phone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" value="<?php echo show($data['dob'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Residential Address</label>
                            <textarea name="address" rows="2" required><?php echo show($data['contact_address'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- 2. ACADEMIC SECTION (All Editable) -->
                    <div class="section-box">
                        <h3><i class="fa-solid fa-graduation-cap"></i> Academic Details</h3>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" value="<?php echo show($data['department'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Class / Year</label>
                            <input type="text" name="class" value="<?php echo show($data['class'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Current Semester</label>
                            <input type="text" name="semester" value="<?php echo show($data['semester'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <!-- 3. STATS SECTION (Now Editable) -->
                    <div class="section-box">
                        <h3><i class="fa-solid fa-chart-line"></i> Academic Standing</h3>
                        <div class="form-group">
                            <label>Cumulative CGPA</label>
                            <input type="text" name="cgpa" value="<?php echo show($data['cgpa'] ?? '8.4'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Attendance Percentage (%)</label>
                            <input type="text" name="prev_attendance" value="<?php echo show($data['prev_attendance'] ?? '95'); ?>" required>
                        </div>
                    </div>

                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                    </button>
                </div>
            </div>
        </form>

    </div>

</div>

<script>
function previewImage(event) {
    const reader = new FileReader();
    reader.onload = function(){
        const output = document.getElementById('photoPreview');
        output.src = reader.result;
    };
    if (event.target.files && event.target.files[0]) {
        reader.readAsDataURL(event.target.files[0]);
    }
}
</script>

</body>
</html>