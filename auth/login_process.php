<?php
// Output buffering initialize panni warnings/headers errors-a prevent panrom
ob_start();

// Production warnings screen-la display aagama hide panrom
error_reporting(0);
ini_set('display_errors', 0);

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db.php'; 

// Multi-location Safe .env File Loader
$env = [];
$possible_env_paths = [
    __DIR__ . '/.env',
    __DIR__ . '/../.env',
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

if (isset($_POST['login'])) {
    
    // 1. Captcha Check
    if (!isset($_SESSION['captcha_code']) || !isset($_POST['captcha']) || strtolower(trim($_POST['captcha'])) !== strtolower($_SESSION['captcha_code'])) {
        echo "<script>alert('Invalid Verification Code (Captcha)! Please try again.'); window.location='login.php';</script>";
        exit;
    }

    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = trim($_POST['role']);
    $reg_no   = trim($_POST['reg_no'] ?? '');

    // Fetch user by email only from Supabase
    $url = $SUPABASE_URL . "/rest/v1/users?email=eq." . urlencode($email) . "&select=*";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch) || $httpCode !== 200) {
        curl_close($ch);
        echo "<script>alert('Server Connection Error. Please try again.'); window.location='login.php';</script>";
        exit;
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    $user = $data[0] ?? null;
    
    // 2. Validate Email, Password & Role Match
    if ($user && password_verify($password, $user['password']) && strtolower($user['role']) === strtolower($role)) {
        
        // STRICT REGISTER NUMBER VALIDATION FOR STUDENT
        if (strtolower($role) === 'student') {
            $db_reg_no = trim($user['reg_no'] ?? '');
            
            if (empty($reg_no) || $db_reg_no !== $reg_no) {
                echo "<script>alert('Invalid Register Number! Please enter your correct Register Number.'); window.location='login.php';</script>";
                exit;
            }
        }

        // Regenerate Session ID for security
        session_regenerate_id(true);

        // Remember Me Logic
        if (isset($_POST['remember'])) {
            setcookie('remember_email', $email, [
                'expires'  => time() + (86400 * 30),
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]); 
        } else {
            if (isset($_COOKIE['remember_email'])) {
                setcookie('remember_email', '', time() - 3600, "/"); 
            }
        }

        // Set Session Data
        $_SESSION['user_id']      = $user['id'] ?? '';
        $_SESSION['role']         = $user['role'];
        $_SESSION['name']         = $user['name'] ?? '';
        $_SESSION['email']        = $user['email'];
        $_SESSION['reg_no']       = $user['reg_no'] ?? ''; 
        $_SESSION['college_code'] = $user['college_code'] ?? '';

        // 3. Role-Based Redirects
        $redirects = [
            "Super Admin" => "../admin/dashboard_admin.php",
            "HOD"         => "../HOD/dashboard_hod.php",
            "Faculty"     => "../faculty/dashboard_faculty.php",
            "Student"     => "../student/dashboard_student.php"
        ];

        if (array_key_exists($role, $redirects)) {
            header("Location: " . $redirects[$role]);
            exit;
        }

    } else {
        echo "<script>alert('Invalid Email, Password or Role'); window.location='login.php';</script>";
        exit;
    }
}
ob_end_flush();
?>