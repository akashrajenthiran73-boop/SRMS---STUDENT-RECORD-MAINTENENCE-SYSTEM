<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Arignar Anna Gov Arts College</title>
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

/* Main Container Layout */
.main-container {
    max-width: 1160px;
    margin: 30px auto;
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 50px;
}

/* Left Side Branding */
.brand-section {
    color: #FFFFFF;
    width: 52%;
    animation: fadeIn 0.8s ease;
}
.brand-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(245, 158, 11, 0.2);
    border: 1px solid rgba(245, 158, 11, 0.4);
    color: #FDE68A;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 18px;
}
.brand-section h1 {
    font-size: 38px;
    margin-bottom: 16px;
    font-weight: 800;
    line-height: 1.25;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.6);
    letter-spacing: -0.5px;
}
.brand-section p {
    color: #F1F5F9;
    font-size: 16px;
    font-weight: 500;
    text-shadow: 0 1px 5px rgba(0, 0, 0, 0.5);
    line-height: 1.6;
}

/* Right Side Forgot Box with Crystal Glassmorphism */
.forgot-box {
    background: rgba(255, 255, 255, 0.88);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    padding: 38px 34px;
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.95);
    width: 100%;
    max-width: 440px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.25);
    position: relative;
    overflow: hidden;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

.forgot-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, #2563EB, #3B82F6, #60A5FA);
}

.forgot-box h2 {
    color: #0F172A;
    text-align: center;
    margin-bottom: 6px;
    font-size: 24px;
    font-weight: 800;
    letter-spacing: -0.3px;
}
.forgot-box .logo-text {
    text-align: center;
    color: #2563EB;
    margin-bottom: 12px;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}
.forgot-box .desc-text {
    text-align: center;
    color: #475569;
    font-size: 13.5px;
    margin-bottom: 24px;
    line-height: 1.5;
}

.input-group {
    position: relative;
    margin-bottom: 18px;
}

.forgot-box input[type="email"] {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #CBD5E1;
    border-radius: 10px;
    background: #F8FAFC;
    color: #0F172A;
    font-size: 14px;
    outline: none;
    transition: all 0.2s ease;
}

.forgot-box input[type="email"]:focus {
    box-shadow: 0 0 0 3.5px rgba(37, 99, 235, 0.12);
    border-color: #2563EB;
    background: #FFFFFF;
}

/* Submit Button */
.forgot-box button {
    width: 100%;
    padding: 13px;
    background: #2563EB;
    border: none;
    border-radius: 10px;
    color: #FFFFFF;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    margin-top: 6px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
}
.forgot-box button:hover {
    background: #1D4ED8;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
}

/* Login Link */
.login-link {
    text-align: center;
    margin-top: 20px;
    color: #475569;
    font-size: 13.5px;
    font-weight: 500;
}
.login-link a {
    color: #2563EB;
    text-decoration: none;
    font-weight: 700;
}
.login-link a:hover {
    text-decoration: underline;
}

@keyframes slideUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Footer */
.footer-note {
    text-align: center;
    color: #FFFFFF;
    font-size: 12.5px;
    font-weight: 500;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.6);
    padding: 12px 0;
}

@media (max-width: 900px) {
    body { padding: 16px; }
    .top-header { flex-direction: column; gap: 10px; text-align: center; }
    .main-container { flex-direction: column; text-align: center; gap: 30px; }
    .brand-section { width: 100%; }
    .brand-section h1 { font-size: 28px; }
    .forgot-box { max-width: 100%; }
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
<div class="main-container">
  
  <!-- Left Branding Section -->
  <div class="brand-section">
    <div class="brand-badge"><i class="fa-solid fa-award"></i> Established 1967</div>
    <h1>Arignar Anna Government Arts & Science College</h1>
    <p>Student Record Maintenance System (SRMS) provides secure access to academic profiles, marks, attendance, and certificates.</p>
  </div>

  <!-- Right Forgot Password Form -->
  <div class="forgot-box">
    <div class="logo-text">STUDENT RECORD MAINTENANCE SYSTEM</div>
    <h2>Reset Password</h2>
    <p class="desc-text">Enter your registered email address below and we'll send you a link to reset your password.</p>
    
    <form method="POST" action="forgot_password_process.php">
      
      <div class="input-group">
        <input type="email" name="email" placeholder="Enter Registered Email" required>
      </div>
      
      <button type="submit" name="reset_request">Send Reset Link / OTP</button>
      
      <div class="login-link">
        Remembered your password? <a href="login.php">Login Here</a>
      </div>
    </form>
  </div>

</div>

<!-- Footer -->
<div class="footer-note">
    &copy; 2026 Arignar Anna Government Arts & Science College. All rights reserved.
</div>

</body>
</html>