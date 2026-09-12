<?php
session_start();
$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SRMS Login Portal - Arignar Anna Government Arts College</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

body {
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.45), rgba(15, 23, 42, 0.65)), url('college_bg.jpg') no-repeat center center/cover fixed;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 24px 32px;
}

/* Top Header */
.top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    padding: 14px 28px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    transition: all 0.3s ease;
}
.college-brand {
    display: flex;
    align-items: center;
    gap: 14px;
}
.college-brand img {
    height: 44px;
    object-fit: contain;
}
.college-brand h2 {
    font-size: 16px;
    color: #1E3A8A;
    font-weight: 800;
    letter-spacing: -0.2px;
}
.portal-badge {
    font-size: 13px;
    font-weight: 700;
    color: #1E40AF;
    background: #EFF6FF;
    padding: 6px 16px;
    border-radius: 30px;
    border: 1px solid #BFDBFE;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 6px rgba(37, 99, 235, 0.08);
}

/* Main Container */
.main-wrapper {
    max-width: 1240px;
    margin: 30px auto;
    width: 100%;
}
.welcome-header {
    text-align: center;
    margin-bottom: 35px;
}
.welcome-header h1 {
    font-size: 36px;
    color: #FFFFFF;
    font-weight: 800;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.6);
    letter-spacing: -0.5px;
}
.welcome-header p {
    color: #F1F5F9;
    font-size: 15px;
    margin-top: 8px;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.6);
    font-weight: 500;
}

/* 4 Cards Grid Layout */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
}

/* Crystal Glassmorphism Cards */
.role-card {
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    padding: 32px 24px;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.role-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
}

.role-card.super-admin::before { background: linear-gradient(90deg, #7C3AED, #A855F7); }
.role-card.hod::before { background: linear-gradient(90deg, #2563EB, #60A5FA); }
.role-card.faculty::before { background: linear-gradient(90deg, #0D9488, #2DD4BF); }
.role-card.student::before { background: linear-gradient(90deg, #EA580C, #FB923C); }

.role-card:hover {
    transform: translateY(-8px);
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    border-color: #FFFFFF;
}

.icon-box {
    width: 70px;
    height: 70px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px auto;
    font-size: 28px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s ease;
}

.role-card:hover .icon-box {
    transform: scale(1.08) rotate(3deg);
}

.role-card.super-admin .icon-box { background: linear-gradient(135deg, #F3E8FF, #DDD6FE); color: #7C3AED; }
.role-card.hod .icon-box { background: linear-gradient(135deg, #EFF6FF, #DBEAFE); color: #2563EB; }
.role-card.faculty .icon-box { background: linear-gradient(135deg, #F0FDFA, #CCFBF1); color: #0D9488; }
.role-card.student .icon-box { background: linear-gradient(135deg, #FFF7ED, #FFEDD5); color: #EA580C; }

.role-card h3 {
    font-size: 20px;
    font-weight: 800;
    color: #0F172A;
    margin-bottom: 10px;
    letter-spacing: -0.3px;
}

.role-card p {
    font-size: 13.5px;
    color: #475569;
    font-weight: 500;
    line-height: 1.55;
    margin-bottom: 24px;
    min-height: 42px;
}

.card-btn {
    width: 100%;
    padding: 13px;
    border-radius: 12px;
    font-size: 14.5px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.25s ease;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
}

.role-card.super-admin .card-btn { background: #7C3AED; color: #fff; }
.role-card.super-admin .card-btn:hover { background: #6D28D9; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(124, 58, 237, 0.35); }

.role-card.hod .card-btn { background: #2563EB; color: #fff; }
.role-card.hod .card-btn:hover { background: #1D4ED8; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(37, 99, 235, 0.35); }

.role-card.faculty .card-btn { background: #0D9488; color: #fff; }
.role-card.faculty .card-btn:hover { background: #0F766E; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(13, 148, 136, 0.35); }

.role-card.student .card-btn { background: #EA580C; color: #fff; }
.role-card.student .card-btn:hover { background: #C2410C; transform: translateY(-2px); box-shadow: 0 6px 18px rgba(234, 88, 12, 0.35); }

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 20px;
    animation: fadeIn 0.25s ease;
}

.modal-box {
    background: #FFFFFF;
    padding: 36px 32px;
    border-radius: 20px;
    width: 100%;
    max-width: 430px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    position: relative;
    animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    border: 1px solid #E2E8F0;
}

.close-modal {
    position: absolute;
    right: 20px;
    top: 20px;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 18px;
    color: #64748B;
    background: #F1F5F9;
    cursor: pointer;
    transition: all 0.2s ease;
}
.close-modal:hover { color: #0F172A; background: #E2E8F0; }

.modal-box h2 {
    color: #1E3A8A;
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
    letter-spacing: -0.3px;
}
.modal-box p {
    font-size: 13.5px;
    color: #64748B;
    margin-bottom: 22px;
}

/* Form inputs */
.input-group {
    margin-bottom: 16px;
    position: relative;
}
.input-group input {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    font-size: 14px;
    color: #0F172A;
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s ease;
}
.input-group input:focus {
    border-color: #2563EB;
    background: #FFFFFF;
    box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
}
.toggle-pwd {
    position: absolute;
    right: 16px;
    top: 14px;
    color: #94A3B8;
    cursor: pointer;
    font-size: 14px;
    transition: color 0.2s ease;
}
.toggle-pwd:hover { color: #1E293B; }

.verify-box {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    align-items: center;
}
.verify-box input {
    flex: 1;
    padding: 12px 16px;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    font-size: 14px;
    background: #F8FAFC;
    outline: none;
    transition: all 0.2s;
}
.verify-box input:focus {
    border-color: #2563EB;
    background: #FFFFFF;
    box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
}
.captcha-image {
    height: 44px;
    border-radius: 10px;
    cursor: pointer;
    border: 1px solid #CBD5E1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease;
}
.captcha-image:hover { transform: scale(1.02); }

.options-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    margin-bottom: 22px;
    color: #475569;
}
.options-bar label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: 500;
}
.options-bar input[type="checkbox"] {
    accent-color: #2563EB;
    width: 16px;
    height: 16px;
}
.options-bar a {
    color: #2563EB;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;
}
.options-bar a:hover { color: #1D4ED8; text-decoration: underline; }

.submit-btn {
    width: 100%;
    padding: 13px;
    background: #2563EB;
    color: #FFFFFF;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}
.submit-btn:hover {
    background: #1D4ED8;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
}

.reg-text {
    text-align: center;
    margin-top: 18px;
    font-size: 13.5px;
    color: #64748B;
}
.reg-text a {
    color: #2563EB;
    font-weight: 700;
    text-decoration: none;
}
.reg-text a:hover { text-decoration: underline; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

.footer-note {
    text-align: center;
    color: #FFFFFF;
    font-size: 12.5px;
    font-weight: 500;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    padding: 12px 0;
}

@media (max-width: 768px) {
    body { padding: 16px; }
    .top-header { flex-direction: column; gap: 10px; text-align: center; }
    .welcome-header h1 { font-size: 26px; }
    .cards-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- Top Header -->
<div class="top-header">
    <div class="college-brand">
        <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
        <h2>Arignar Anna Government Arts College, Villupuram</h2>
    </div>
    <div class="portal-badge">👑 SRMS Portal</div>
</div>

<!-- Main Container -->
<div class="main-wrapper">
    <div class="welcome-header">
        <h1>College Academic Portal</h1>
        <p>Arignar Anna Government Arts College &bull; Student Record Maintenance System</p>
        <div style="display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 14px;">
            <span style="background: rgba(37, 99, 235, 0.22); border: 1px solid rgba(147, 197, 253, 0.5); color: #FFFFFF; font-size: 11.5px; font-weight: 700; padding: 5px 14px; border-radius: 20px; letter-spacing: 0.3px;"><i class="fa-solid fa-code" style="color: #60A5FA;"></i> Computer Science (B.Sc CS)</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">BCA</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">Mathematics</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">Physics</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">Chemistry</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">Commerce</span>
            <span style="background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.2); color: #E2E8F0; font-size: 11.5px; font-weight: 600; padding: 5px 11px; border-radius: 20px;">Arts & Humanities</span>
        </div>
    </div>

    <div class="cards-grid">
        <div class="role-card super-admin">
            <div class="icon-box"><i class="fa-solid fa-crown"></i></div>
            <h3>Super Admin</h3>
            <p>Full system control, manage users, permissions and global settings.</p>
            <button class="card-btn" onclick="openLoginModal('Super Admin')">Admin Login</button>
        </div>

        <div class="role-card hod">
            <div class="icon-box"><i class="fa-solid fa-user-tie"></i></div>
            <h3>HOD</h3>
            <p>Manage department activities, verify records and review performance.</p>
            <button class="card-btn" onclick="openLoginModal('HOD')">HOD Login</button>
        </div>

        <div class="role-card faculty">
            <div class="icon-box"><i class="fa-solid fa-chalkboard-user"></i></div>
            <h3>Faculty</h3>
            <p>Handle student attendance, internal marks, and academic logs.</p>
            <button class="card-btn" onclick="openLoginModal('Faculty')">Faculty Login</button>
        </div>

        <div class="role-card student">
            <div class="icon-box"><i class="fa-solid fa-graduation-cap"></i></div>
            <h3>Student</h3>
            <p>Access your dashboard, view records, results and update profile.</p>
            <button class="card-btn" onclick="openLoginModal('Student')">Student Login</button>
        </div>
    </div>
</div>

<!-- Hidden Login Modal Pop-up -->
<div class="modal-overlay" id="loginModal">
    <div class="modal-box">
        <span class="close-modal" onclick="closeLoginModal()">&times;</span>
        <h2 id="modalTitle">Login Portal</h2>
        <p id="modalSubtitle">Please enter your credentials to continue</p>

        <form method="POST" action="login_process.php">
            <input type="hidden" name="role" id="selectedRoleInput">

            <div class="input-group">
                <input type="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($remembered_email); ?>" required>
            </div>

            <!-- REGISTER NUMBER FIELD FOR STUDENT LOGIN (Dynamic Show/Hide) -->
            <div class="input-group" id="regNoContainer" style="display: none;">
                <input type="text" name="reg_no" id="reg_no_input" placeholder="Enter your Register Number">
            </div>

            <div class="input-group">
                <input type="password" name="password" id="modal_pwd" placeholder="Enter your password" required>
                <i class="fa-solid fa-eye toggle-pwd" id="toggleModalPwd"></i>
            </div>

            <div class="verify-box">
                <input type="text" name="captcha" placeholder="Enter Captcha Code" required autocomplete="off">
                <img src="captcha.php" alt="Captcha" class="captcha-image" title="Click to refresh" onclick="this.src='captcha.php?'+Math.random();">
            </div>

            <div class="options-bar">
                <label>
                    <input type="checkbox" name="remember" <?php if($remembered_email != '') echo 'checked'; ?>> Remember me
                </label>
                <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="submit-btn">Sign In Now</button>

            <div class="reg-text">
                Don't have an account? <a href="register.php">Register Now</a>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<div class="footer-note">
    &copy; 2026 Arignar Anna Government Arts College. All rights reserved.
</div>

<script>
    function openLoginModal(roleName) {
        document.getElementById('selectedRoleInput').value = roleName;
        document.getElementById('modalTitle').innerText = roleName + ' Login';
        document.getElementById('modalSubtitle').innerText = 'Signing in as ' + roleName;
        
        // Dynamic Toggle for Student Register Number
        const regContainer = document.getElementById('regNoContainer');
        const regInput = document.getElementById('reg_no_input');
        
        if (roleName.toLowerCase() === 'student') {
            regContainer.style.display = 'block';
            regInput.setAttribute('required', 'required');
        } else {
            regContainer.style.display = 'none';
            regInput.removeAttribute('required');
            regInput.value = '';
        }

        document.getElementById('loginModal').style.display = 'flex';
    }

    function closeLoginModal() {
        document.getElementById('loginModal').style.display = 'none';
    }

    window.onclick = function(event) {
        let modal = document.getElementById('loginModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    const togglePwd = document.querySelector('#toggleModalPwd');
    const pwdField = document.querySelector('#modal_pwd');

    if(togglePwd && pwdField) {
        togglePwd.addEventListener('click', function () {
            const type = pwdField.getAttribute('type') === 'password' ? 'text' : 'password';
            pwdField.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    }
</script>

</body>
</html>