<?php 
session_start(); 
if(!isset($_SESSION['user_id'])){ 
    header("Location: login.php"); 
    exit(); 
} 
$role = 'Admin'; 

// Database Connection Integration
// include('../db.php'); 

$total_students = 1240; 
$umis_fields_count = "";
$result_analysis = "View";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

body {
    background-color: #F8FAFC;
    color: #0F172A;
    display: flex;
    min-height: 100vh;
}

/* Modern Sidebar with custom scrollbar */
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: #0B132B;
    color: white;
    padding: 22px 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-shadow: 4px 0 25px rgba(0, 0, 0, 0.06);
    z-index: 100;
}

.sidebar-brand {
    text-align: center;
    padding-bottom: 18px;
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
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
    display: block;
    margin-top: 4px;
}

.sidebar-nav-container {
    flex-grow: 1;
    overflow-y: auto;
    margin-top: 14px;
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
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #94A3B8;
    padding: 10px 14px;
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.25s ease;
}

.sidebar-menu a:hover {
    color: #F8FAFC;
    background: rgba(255, 255, 255, 0.06);
    transform: translateX(3px);
}

.sidebar-menu a.active {
    background: rgba(245, 158, 11, 0.14);
    color: #F59E0B;
    font-weight: 700;
}

.sidebar-menu a i {
    font-size: 15px;
    width: 20px;
    text-align: center;
}

.menu-divider {
    margin: 14px 8px;
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

/* Main Content Wrapper */
.main-content {
    margin-left: 260px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* Top Navigation Bar */
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

/* Content Body */
.content-body {
    padding: 35px 40px;
    flex: 1;
}

.section-title {
    font-size: 16px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Dashboard Cards Grid Container */
.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 25px;
    margin-bottom: 35px;
}

.card {
    position: relative;
    overflow: hidden;
    border-radius: 18px;
    padding: 28px 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    color: #FFFFFF;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
    transition: 0.6s;
}

.card:hover::before {
    left: 100%;
}

.card:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 35px rgba(0, 0, 0, 0.16);
}

.card.c-blue { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }
.card.c-purple { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }
.card.c-emerald { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
.card.c-amber { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }

.card-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin: 0 auto 16px auto;
    background: rgba(255, 255, 255, 0.2);
    color: #FFFFFF;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.card h3 {
    color: rgba(255, 255, 255, 0.92);
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 8px;
}
.card p {
    font-size: 32px;
    font-weight: 800;
    color: #FFFFFF;
    letter-spacing: -0.5px;
}

@media (max-width: 900px) {
    .sidebar { width: 70px; padding: 16px 8px; }
    .sidebar-brand h2 span, .sidebar-brand span, .sidebar-menu a span, .menu-heading { display: none; }
    .sidebar-brand h2 { font-size: 18px; }
    .sidebar-menu a { justify-content: center; padding: 12px; }
    .main-content { margin-left: 70px; }
    .navbar { padding: 16px 20px; }
    .content-body { padding: 20px; }
}
</style>
</head>
<body>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar">
    <div class="sidebar-brand">
        <h2>👑 SRMS</h2>
        <span>Arignar Anna Govt Arts College</span>
    </div>
    
    <!-- SCROLLABLE MENU CONTAINER -->
    <div class="sidebar-nav-container">
        <ul class="sidebar-menu">
            <li><a href="dashboard_admin.php" class="active"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a></li>
            <li><a href="student_records.php"><i class="fa-solid fa-user-graduate"></i> <span>Student Records</span></a></li>
            <li><a href="bio_data.php"><i class="fa-solid fa-id-card"></i> <span>Bio Data</span></a></li>
            <li><a href="umis.php"><i class="fa-solid fa-file-lines"></i> <span>UMIS Details</span></a></li>
            <li><a href="result_analysis.php"><i class="fa-solid fa-chart-pie"></i> <span>Result Analysis</span></a></li>

            <!-- ADMIN EXCLUSIVE MENUS -->
            <hr class="menu-divider">
            <div class="menu-heading">Admin Panel</div>
            <li><a href="manage_users.php"><i class="fa-solid fa-users-gear"></i> <span>Manage Users</span></a></li>
            <li><a href="announcements.php"><i class="fa-solid fa-bullhorn"></i> <span>Announcements</span></a></li>
            <li><a href="backup.php"><i class="fa-solid fa-database"></i> <span>Backup & Restore</span></a></li>
            <li><a href="reports.php"><i class="fa-solid fa-file-lines"></i> <span>Reports</span></a></li>
            <li><a href="support.php"><i class="fa-solid fa-circle-question"></i> <span>Help & Support</span></a></li>
        </ul>
    </div>

    <!-- COMMON FOOTER MENU WITH LOGOUT -->
    <div class="sidebar-footer">
        <ul class="sidebar-menu" style="margin-top: 0;">
            <li><a href="admin_profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
            <li><a href="../auth/logout.php" style="color: #F87171;"><i class="fa-solid fa-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </div>
</div>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-content">
    
    <div class="navbar">
        <h1>Welcome Back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>!</h1>
        <div class="user-profile-badge">
            <i class="fa-solid fa-shield-halved"></i> Role: Admin
        </div>
    </div>

    <div class="content-body">
        
        <div class="section-title"><i class="fa-solid fa-gauge-high"></i> Overview Metrics</div>

        <!-- DASHBOARD STATS CARDS WITH FULL GRADIENTS -->
        <div class="card-container">
          <div class="card c-blue" onclick="location.href='student_records.php'">
            <div class="card-icon"><i class="fa-solid fa-users"></i></div>
            <h3>Students Records Details</h3>
            <p><?php echo number_format($total_students); ?></p>
          </div>
          
          <div class="card c-purple" onclick="location.href='bio_data.php'">
            <div class="card-icon"><i class="fa-solid fa-id-card"></i></div>
            <h3>Bio Data Details</h3>
            <p style="font-size: 22px; color: #FFFFFF; margin-top: 6px;">View Details</p>
          </div>
          
          <div class="card c-emerald" onclick="location.href='umis.php'">
            <div class="card-icon"><i class="fa-solid fa-file-lines"></i></div>
            <h3>UMIS Details</h3>
            <p><?php echo $umis_fields_count; ?> <span style="font-size: 15px; color: rgba(255,255,255,0.9); font-weight: 600;">View Details</span></p>
          </div>
          
          <div class="card c-amber" onclick="location.href='result_analysis.php'">
            <div class="card-icon"><i class="fa-solid fa-chart-pie"></i></div>
            <h3>Result Analysis</h3>
            <p><?php echo $result_analysis; ?></p>
          </div>
        </div>

    </div>

</div>

</body>
</html>