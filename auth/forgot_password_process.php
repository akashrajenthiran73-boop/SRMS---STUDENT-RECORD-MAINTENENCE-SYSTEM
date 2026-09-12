<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = trim($_POST['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Reset Request - SRMS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
body {
    background: #F8FAFC;
    color: #1E293B;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
}
.card {
    background: white;
    max-width: 500px;
    width: 100%;
    border-radius: 20px;
    padding: 36px 30px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
    border: 1px solid #E2E8F0;
    text-align: center;
}
.icon-box {
    width: 72px;
    height: 72px;
    background: #EFF6FF;
    color: #2563EB;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin: 0 auto 20px auto;
}
h2 { font-size: 22px; color: #1E3A8A; font-weight: 800; margin-bottom: 10px; }
p { font-size: 14.5px; color: #64748B; line-height: 1.6; margin-bottom: 24px; }
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #2563EB;
    color: white;
    padding: 12px 26px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.2s;
}
.btn:hover { background: #1D4ED8; transform: translateY(-1px); }
</style>
</head>
<body>
<div class="card">
    <div class="icon-box"><i class="fa-solid fa-envelope-circle-check"></i></div>
    <h2>Reset Instructions Sent</h2>
    <p>
        If an account with <strong><?=htmlspecialchars($email ?: 'your email')?></strong> exists in the SRMS college directory, password reset instructions or admin notification has been generated.
    </p>
    <p style="font-size: 13px; background: #F1F5F9; padding: 12px; border-radius: 8px; color: #475569;">
        <i class="fa-solid fa-circle-info" style="color: #2563EB;"></i> For immediate assistance, please contact the College Examination / SRMS System Administrator.
    </p>
    <a href="login.php" class="btn"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
</div>
</body>
</html>
