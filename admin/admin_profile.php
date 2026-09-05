<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Safe Session & Super Admin Role Check using user_id
$session_user_id = $_SESSION['user_id'] ?? '';
$session_role = trim($_SESSION['role'] ?? '');

$is_super_admin = (strcasecmp($session_role, 'Super Admin') === 0 || strcasecmp($session_role, 'SuperAdmin') === 0 || strcasecmp($session_role, 'super_admin') === 0);

if (empty($session_user_id) || !$is_super_admin) {
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

$msg = '';
$error = '';

// Handle Form Actions using user_id
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Update Editable Profile Info (Name, ID, Email, Phone, DOB, Joining Date, Address)
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $admin_id = trim($_POST['admin_id'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $joining_date = trim($_POST['joining_date'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($email && $name) {
            $payload = [
                'name' => $name,
                'id' => $admin_id,
                'email' => $email,
                'phone' => $phone,
                'dob' => $dob,
                'joining_date' => $joining_date,
                'address' => $address
            ];
            list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
            
            if ($http >= 200 && $http < 300) {
                $_SESSION['name'] = $name; // Update session name if changed
                $msg = "Profile updated successfully!";
            } else {
                $error = "Failed to update profile. Email or ID might already exist.";
            }
        } else {
            $error = "Name and Email are required.";
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
    // 3. Notification Settings
    elseif ($action === 'update_settings') {
        $notif = isset($_POST['notifications']) ? 'Enabled' : 'Disabled';
        $payload = ['notification_prefs' => $notif];
        
        list($res, $http) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id), $SUPABASE_KEY, 'PATCH', $payload);
        if ($http >= 200 && $http < 300) {
            $msg = "Notification preferences updated!";
        } else {
            $error = "Failed to update settings.";
        }
    }
    // 4. Photo Upload Handling
    elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'admin_' . time() . '.' . $fileExtension;
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
}

// Fetch Admin Details from Supabase using user_id Safely
$admin = [];
if (!empty($session_user_id)) {
    list($users, $http_code) = callSupabase(rtrim($SUPABASE_URL, '/') . "/rest/v1/users?id=eq." . urlencode($session_user_id) . "&select=*", $SUPABASE_KEY, 'GET');
    $admin = (!empty($users)) ? $users[0] : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Profile - SRMS Panel</title>
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

/* Sidebar Navigation */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #0B132B;
    color: white;
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.05);
    z-index: 100;
}

.sidebar-brand {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}
.sidebar-brand h2 {
    color: #F59E0B;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.sidebar-brand span {
    font-size: 11px;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    display: block;
    margin-top: 4px;
}

.sidebar-nav-container {
    flex-grow: 1;
    overflow-y: auto;
    margin-top: 15px;
    padding-right: 4px;
}

.sidebar-nav-container::-webkit-scrollbar {
    width: 4px;
}
.sidebar-nav-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 4px;
}

.sidebar-menu {
    list-style: none;
}

.sidebar-menu li {
    margin-bottom: 4px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 11px 16px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 500;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.sidebar-menu a:hover {
    color: #F8FAFC;
    background: rgba(255, 255, 255, 0.06);
}

.sidebar-menu a.active {
    background: #2563EB;
    color: #FFFFFF;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.sidebar-menu a i {
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.menu-divider {
    margin: 14px 10px;
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.menu-heading {
    font-size: 10px;
    text-transform: uppercase;
    color: #64748B;
    padding: 6px 14px;
    letter-spacing: 1.2px;
    font-weight: 700;
}

.sidebar-footer {
    flex-shrink: 0;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding-top: 12px;
}

/* Main Content Area */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.navbar {
    background: #FFFFFF;
    padding: 18px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #E2E8F0;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
    position: sticky;
    top: 0;
    z-index: 50;
}
.navbar h1 {
    color: #1E3A8A;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.3px;
}
.user-profile-badge {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #EFF6FF;
    padding: 7px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 700;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
}
.user-profile-badge i {
    color: #2563EB;
}

.content-body {
    padding: 35px 40px;
    flex: 1;
}

.profile-container {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 28px;
    align-items: start;
}

.profile-card-left {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.avatar-box {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 16px;
    background: #F1F5F9;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #BFDBFE;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.15);
}
.avatar-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-box i {
    font-size: 48px;
    color: #94A3B8;
}

.profile-card-left h3 {
    font-size: 18px;
    color: #1E3A8A;
    font-weight: 800;
    margin-bottom: 4px;
}
.profile-card-left p {
    font-size: 13px;
    color: #64748B;
    margin-bottom: 12px;
}

.badge-admin {
    background: #EFF6FF;
    color: #1E40AF;
    border: 1px solid #BFDBFE;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 18px;
}

.card-section {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 18px;
    padding: 28px;
    margin-bottom: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
}

.card-section h4 {
    font-size: 16px;
    color: #1E3A8A;
    margin-bottom: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1.5px solid #F1F5F9;
    padding-bottom: 12px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 16px;
}
.form-group.full {
    grid-column: span 2;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 6px;
}
.form-group input, .form-group select {
    width: 100%;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1.5px solid #E2E8F0;
    font-size: 13.5px;
    outline: none;
    background: #FFFFFF;
    transition: all 0.2s ease;
}
.form-group input:focus {
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13.5px;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.25s ease;
}

.btn-primary {
    background: #2563EB;
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
}
.btn-primary:hover {
    background: #1D4ED8;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    padding: 12px 18px;
    border-radius: 10px;
    border: 1px solid #A7F3D0;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-danger {
    background: #FEF2F2;
    color: #991B1B;
    padding: 12px 18px;
    border-radius: 10px;
    border: 1px solid #FECACA;
    font-size: 13.5px;
    font-weight: 600;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 20px 8px; }
    .sidebar-brand span, .sidebar-menu span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
    .profile-container { grid-template-columns: 1fr; }
    .form-grid { grid-template-columns: 1fr; }
    .form-group.full { grid-column: span 1; }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Arignar Anna College</span>
    </div>
    
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="admin_profile.php" class="active"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="navbar">
        <h1>Profile & Account Settings</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: <?php echo htmlspecialchars($session_role); ?>
        </div>
    </div>

    <div class="content-body">
        
        <?php if($msg): ?><div class="alert-success"><i class="fa-solid fa-circle-check"></i> <?=$msg?></div><?php endif; ?>
        <?php if($error): ?><div class="alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?=$error?></div><?php endif; ?>

        <div class="profile-container">
            <!-- Left Side: Basic Photo & Summary -->
            <div class="profile-card-left">
                <div class="avatar-box">
                    <?php if(!empty($admin['photo'])): ?>
                        <img src="../uploads/<?=htmlspecialchars($admin['photo'])?>" alt="Admin Photo">
                    <?php else: ?>
                        <i class="fa-solid fa-user-tie"></i>
                    <?php endif; ?>
                </div>
                <h3><?=htmlspecialchars($admin['name'] ?? ($_SESSION['name'] ?? 'Super Admin'))?></h3>
                <p><?=htmlspecialchars($admin['email'] ?? '')?></p>
                <span class="badge-admin"><i class="fa-solid fa-shield"></i> Super Admin</span>
                
                <!-- Photo Upload Form -->
                <form method="POST" enctype="multipart/form-data" style="margin-top:10px;">
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="form-group" style="text-align:left;">
                        <label style="font-size:12px;">Change Avatar</label>
                        <input type="file" name="photo" required style="font-size:12px; padding:6px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:9px; font-size:13px;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo</button>
                </form>
            </div>

            <!-- Right Side: Details & Forms -->
            <div>
                <!-- 1. Basic & Work Info Section -->
                <div class="card-section">
                    <h4><i class="fa-solid fa-id-card" style="color: #2563EB;"></i> Basic & Work Details</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" value="<?=htmlspecialchars($admin['name'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Admin ID</label>
                                <input type="text" name="admin_id" value="<?=htmlspecialchars($admin['id'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?=htmlspecialchars($admin['email'] ?? '')?>" required>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?=htmlspecialchars($admin['phone'] ?? '')?>" placeholder="Enter phone number">
                            </div>
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <input type="text" name="dob" value="<?=htmlspecialchars($admin['dob'] ?? '')?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="form-group">
                                <label>Joining Date</label>
                                <input type="text" name="joining_date" value="<?=htmlspecialchars($admin['joining_date'] ?? '')?>" placeholder="YYYY-MM-DD">
                            </div>
                            <div class="form-group full">
                                <label>Address</label>
                                <input type="text" name="address" value="<?=htmlspecialchars($admin['address'] ?? '')?>" placeholder="Enter full address">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px;"><i class="fa-solid fa-floppy-disk"></i> Save Profile Changes</button>
                    </form>
                </div>

                <!-- 2. Account & Last Login Info -->
                <div class="card-section">
                    <h4><i class="fa-solid fa-shield-halved" style="color: #2563EB;"></i> Account Security & Password</h4>
                    <p style="font-size:12.5px; color:#64748B; margin-bottom:16px;"><b>Last Login:</b> <?=htmlspecialchars($admin['last_login'] ?? 'Active Now')?></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required placeholder="Current password">
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required placeholder="New password">
                            </div>
                            <div class="form-group full">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required placeholder="Confirm new password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:10px; background:#0F172A;"><i class="fa-solid fa-key"></i> Update Password</button>
                    </form>
                </div>

                <!-- 3. Notification Settings -->
                <div class="card-section">
                    <h4><i class="fa-solid fa-bell" style="color: #2563EB;"></i> System Notifications</h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_settings">
                        <div class="form-group" style="display:flex; align-items:center; gap:12px;">
                            <input type="checkbox" name="notifications" id="notif" style="width:18px; height:18px; cursor:pointer;" <?=(($admin['notification_prefs'] ?? 'Enabled') === 'Enabled') ? 'checked' : ''?>>
                            <label for="notif" style="margin-bottom:0; cursor:pointer; font-size: 13.5px; color: #334155;">Enable email notifications for system updates and reports</label>
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:8px; font-size:13px;"><i class="fa-solid fa-check"></i> Save Preferences</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>