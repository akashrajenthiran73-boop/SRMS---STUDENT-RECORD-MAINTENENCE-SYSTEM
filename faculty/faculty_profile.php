<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Safe Session & Faculty Role Check
$session_user_id = $_SESSION['user_id'] ?? '';
$session_role = trim($_SESSION['role'] ?? '');

$is_faculty = (strcasecmp($session_role, 'Faculty') === 0 || strcasecmp($session_role, 'Staff') === 0);

if (empty($session_user_id) || !$is_faculty) {
    header("Location: ../auth/login.php");
    exit;
}

// 1. Safe Multi-location .env File Loader
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

function callSupabase($url, $key, $method = 'GET', $data = []) {
    if (empty($url) || empty($key)) {
        return [[], 0];
    }
    $ch = curl_init($url);
    $headers = [
        "apikey: $key", 
        "Authorization: Bearer $key",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH') {
        $options[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($res, true), $http];
}

// Fetch Faculty Details First so $faculty_email is available everywhere
$faculty = [];
if (!empty($session_user_id)) {
    list($users, $http_code) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=*", $SUPABASE_KEY, 'GET');
    $faculty = (!empty($users)) ? $users[0] : [];
}

$faculty_name = $faculty['name'] ?? 'Faculty Member';
$faculty_email = $faculty['email'] ?? '';
$faculty_dept = $faculty['department'] ?? '';

$msg = '';
$error = '';

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Profile Info
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $faculty_id = trim($_POST['faculty_id'] ?? '');
        $employee_id = trim($_POST['employee_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $joining_date = trim($_POST['joining_date'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $qualification = trim($_POST['qualification'] ?? '');

        $payload = [
            'name' => $name,
            'faculty_id' => $faculty_id,
            'employee_id' => $employee_id,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'designation' => $designation,
            'joining_date' => $joining_date,
            'dob' => $dob,
            'address' => $address,
            'qualification' => $qualification
        ];

        list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
        
        if ($http >= 200 && $http < 300) {
            $_SESSION['name'] = $name;
            $msg = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    } 
    // 2. Change Password
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            list($users, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=password", $SUPABASE_KEY, 'GET');
            
            if (!empty($users) && password_verify($current_password, $users[0]['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $payload = ['password' => $hashed_password];
                
                list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
                
                if ($http >= 200 && $http < 300) {
                    $msg = "Password changed successfully!";
                } else {
                    $error = "Failed to update password.";
                }
            } else {
                $error = "Incorrect current password.";
            }
        }
    }
    // 3. Photo Upload
    elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'faculty_' . time() . '.' . $fileExtension;
                $uploadFileDir = '../uploads/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $dest_path = $uploadFileDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $payload = ['photo' => $newFileName];
                    list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
                    if ($http >= 200 && $http < 300) {
                        $msg = "Profile photo uploaded successfully!";
                    } else {
                        $error = "Failed to update photo path in database.";
                    }
                } else {
                    $error = "Error moving the uploaded file.";
                }
            } else {
                $error = "Invalid file type. Only JPG, JPEG, PNG, WEBP allowed.";
            }
        } else {
            $error = "Please choose a valid image file.";
        }
    }
    // 4. Add Subject Handling
    elseif ($action === 'add_subject') {
        $sub_name = trim($_POST['subject_name'] ?? '');
        $sub_code = trim($_POST['subject_code'] ?? '');
        
        if (!empty($sub_name) && !empty($sub_code) && !empty($faculty_email)) {
            $payload = [
                'subject_name' => $sub_name,
                'subject_code' => $sub_code,
                'faculty_email' => $faculty_email
            ];

            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects", $SUPABASE_KEY, 'POST', $payload);
            
            if ($http >= 200 && $http < 300) {
                $msg = "Subject added successfully!";
                header("Refresh:0");
                exit;
            } else {
                $error = "Failed to add subject.";
            }
        } else {
            $error = "Please fill in all subject fields or check user email.";
        }
    }
}

// Fetch Subjects Handling list linked by faculty email
$subjects_list = [];
if (!empty($faculty_email)) {
    list($subs, $s_http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/subjects?faculty_email=eq." . urlencode($faculty_email) . "&select=subject_name,subject_code", $SUPABASE_KEY, 'GET');
    if ($s_http >= 200 && $s_http < 300 && is_array($subs)) {
        $subjects_list = $subs;
    }
}

// Stats (Total Classes & Attendance % placeholders or fetched from analytics)
$total_classes = 42; // Example count or custom fetch query
$attendance_pct = "94.5%"; // Example average stats
$leave_balance = $faculty['leave_balance'] ?? '12 Days';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty Profile - SRMS Portal</title>
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

/* Page Layout */
.content-body { padding: 30px 36px; }

.alert-success { background: #DCFCE7; color: #15803D; padding: 14px 18px; border-radius: 12px; border: 1px solid #BBF7D0; font-size: 13.5px; font-weight: 600; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; }
.alert-danger { background: #FEE2E2; color: #B91C1C; padding: 14px 18px; border-radius: 12px; border: 1px solid #FECACA; font-size: 13.5px; font-weight: 600; margin-bottom: 22px; display: flex; align-items: center; gap: 10px; }

.profile-container { display: grid; grid-template-columns: 320px 1fr; gap: 26px; }
@media (max-width: 1024px) {
    .profile-container { grid-template-columns: 1fr; }
}

.profile-card-left { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 26px; text-align: center; height: fit-content; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.avatar-box { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 16px; background: #F1F5F9; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 3px solid #2563EB; box-shadow: 0 4px 12px rgba(37,99,235,0.2); }
.avatar-box img { width: 100%; height: 100%; object-fit: cover; }
.avatar-box i { font-size: 52px; color: #94A3B8; }

.profile-card-left h3 { font-size: 17px; color: #0F172A; font-weight: 800; margin-bottom: 4px; }
.profile-card-left p { font-size: 13px; color: #64748B; margin-bottom: 14px; word-break: break-all; }
.badge-faculty { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; padding: 4px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; display: inline-block; margin-bottom: 18px; }

.card-section { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9; }
.card-title { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 700; color: #0F172A; }
.card-title i { color: #2563EB; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
.form-group { margin-bottom: 14px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 5px; }
.form-group input { width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid #CBD5E1; font-size: 13px; outline: none; background: #FFFFFF; color: #0F172A; transition: border-color 0.2s; font-family: inherit; }
.form-group input:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12); }

.btn { padding: 9px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 13px; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
.btn-primary { background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFFFFF; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
.btn-primary:hover { opacity: 0.95; transform: translateY(-1px); }

.tag-list { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.tag { background: #EEF2FF; color: #4338CA; border: 1px solid #E0E7FF; padding: 6px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
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
            <li><a href="timetable.php"><i class="fa-solid fa-calendar-days"></i> Timetable</a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bell"></i> Announcements</a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> Help & Support</a></li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <a href="faculty_profile.php" class="sidebar-menu active" style="display:flex; align-items:center; gap:12px; color:#FFFFFF; background:#2563EB; padding:9px 12px; text-decoration:none; font-size:13px; font-weight:600; border-radius:9px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);">
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
            <h1>Faculty Profile Dashboard</h1>
            <p>Manage personal credentials, contact details, assigned subjects, and password</p>
        </div>
        <div class="user-profile">
            <div class="role-badge">
                <i class="fa-solid fa-user-tie"></i> Faculty Access
            </div>
            <div style="font-size: 13.5px; font-weight: 600; color: #334155;">
                <?php echo htmlspecialchars($faculty_name); ?>
            </div>
        </div>
    </div>

    <div class="content-body">

        <?php if($msg): ?><div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div><?php endif; ?>
        <?php if($error): ?><div class="alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=$error?></div><?php endif; ?>

        <div class="profile-container">
            <!-- Left Side: Photo & Summary -->
            <div class="profile-card-left">
                <div class="avatar-box">
                    <?php if(!empty($faculty['photo'])): ?>
                        <img src="../uploads/<?=htmlspecialchars($faculty['photo'])?>" alt="Faculty Photo">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <h3><?=htmlspecialchars($faculty_name)?></h3>
                <p><?=htmlspecialchars($faculty_email)?></p>
                <span class="badge-faculty"><i class="fa-solid fa-award"></i> Role: Faculty</span>
                
                <!-- Photo Upload Form -->
                <form method="POST" enctype="multipart/form-data" style="margin-top:14px; border-top:1px solid #F1F5F9; padding-top:16px;">
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="form-group" style="text-align:left;">
                        <label for="photo" style="font-size:11.5px; font-weight:600; color:#475569;">Update Profile Picture</label>
                        <input type="file" id="photo" name="photo" required style="font-size:12px; padding:6px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:9px; font-size:12.5px;">
                        <i class="fa-solid fa-camera"></i> Upload Photo
                    </button>
                </form>
            </div>

            <!-- Right Side: Details & Settings -->
            <div>
                <!-- 1. Profile Details Form (Basic & Work & Academic) -->
                <div class="card-section">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-id-card"></i>
                            <span>Personal, Work & Academic Details</span>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?=htmlspecialchars($faculty['name'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label for="faculty_id">Faculty ID</label>
                                <input type="text" id="faculty_id" name="faculty_id" value="<?=htmlspecialchars($faculty['faculty_id'] ?? ($faculty['id'] ?? ''))?>" required>
                            </div>
                            <div class="form-group">
                                <label for="employee_id">Employee ID</label>
                                <input type="text" id="employee_id" name="employee_id" value="<?=htmlspecialchars($faculty['employee_id'] ?? '')?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?=htmlspecialchars($faculty['email'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone" value="<?=htmlspecialchars($faculty['phone'] ?? '')?>" placeholder="Enter phone">
                            </div>
                            <div class="form-group">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?=htmlspecialchars($faculty['department'] ?? '')?>" placeholder="Enter Department">
                            </div>
                            <div class="form-group">
                                <label for="designation">Designation</label>
                                <input type="text" id="designation" name="designation" value="<?=htmlspecialchars($faculty['designation'] ?? 'Faculty')?>">
                            </div>
                            <div class="form-group">
                                <label for="joining_date">Joining Date</label>
                                <input type="date" id="joining_date" name="joining_date" value="<?=htmlspecialchars($faculty['joining_date'] ?? '')?>">
                            </div>
                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input type="date" id="dob" name="dob" value="<?=htmlspecialchars($faculty['dob'] ?? '')?>">
                            </div>
                            <div class="form-group">
                                <label for="qualification">Qualification</label>
                                <input type="text" id="qualification" name="qualification" value="<?=htmlspecialchars($faculty['qualification'] ?? '')?>" placeholder="e.g. M.E., Ph.D">
                            </div>
                            <div class="form-group full">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" value="<?=htmlspecialchars($faculty['address'] ?? '')?>" placeholder="Enter full address">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;">
                            <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                        </button>
                    </form>
                </div>

                <!-- 2. Change Password Form -->
                <div class="card-section">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-lock"></i>
                            <span>Change Account Password</span>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                            </div>
                            <div class="form-group full">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:6px;">
                            <i class="fa-solid fa-key"></i> Update Password
                        </button>
                    </form>
                </div>

                <!-- 3. Subjects Handling List & Add Form -->
                <div class="card-section">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-book-bookmark"></i>
                            <span>Subjects I Handle</span>
                        </div>
                    </div>
                    
                    <!-- Display Subjects -->
                    <div class="tag-list">
                        <?php if(!empty($subjects_list)): ?>
                            <?php foreach($subjects_list as $sub): ?>
                                <span class="tag"><i class="fa-solid fa-book"></i> <?=htmlspecialchars($sub['subject_name'])?> (<?=htmlspecialchars($sub['subject_code'])?>)</span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="font-size:13px; color:#64748B;">No subjects added yet.</span>
                        <?php endif; ?>
                    </div>

                    <!-- Add Subject Form -->
                    <form method="POST">
                        <input type="hidden" name="action" value="add_subject">
                        <div class="form-grid" style="grid-template-columns: 1fr 1fr 110px; align-items: end;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="subject_name">Subject Name</label>
                                <input type="text" id="subject_name" name="subject_name" required placeholder="e.g. Java Programming">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="subject_code">Subject Code</label>
                                <input type="text" id="subject_code" name="subject_code" required placeholder="e.g. CS101">
                            </div>
                            <button type="submit" class="btn btn-primary" style="height:38px; justify-content:center;">
                                <i class="fa-solid fa-plus"></i> Add
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>