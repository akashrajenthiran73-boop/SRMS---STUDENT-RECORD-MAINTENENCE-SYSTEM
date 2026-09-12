<?php
// Safe session initialization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$DEFAULT_SUPABASE_URL = 'https://edwndgdjzjevbgdliuxy.supabase.co';
$DEFAULT_SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImVkd25kZ2RqempldmJnZGxpdXh5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODUyNDg1ODgsImV4cCI6MjEwMDgyNDU4OH0.yvqU6cT-xbbLezB4PaXd3lufrfdzN2OwnVzOO7new_c';

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? $DEFAULT_SUPABASE_URL)); 
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY') ?: ($_ENV['SUPABASE_ANON_KEY'] ?? $DEFAULT_SUPABASE_KEY));


if (isset($_POST['register'])) {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $role       = trim($_POST['role']);
    $department = trim($_POST['department'] ?? 'Computer Science');
    $password   = $_POST['password'];
    // Register Number capturing from Form
    $reg_no     = trim($_POST['reg_no'] ?? '');

    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // 1. Check if Email or Register Number already exists in 'users' table
    $check_url = $SUPABASE_URL . "/rest/v1/users?or=(email.eq." . urlencode($email) . ",reg_no.eq." . urlencode($reg_no) . ")&select=*";
    
    $ch = curl_init($check_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $existing_user = json_decode($response, true);

    if (!empty($existing_user)) {
        echo "<script>alert('This Email ID or Register Number is already registered! Please Login.'); window.location='login.php';</script>";
        exit;
    }

    // 2. Insert new user with 'reg_no' and 'department' into Supabase Database
    $insert_url = $SUPABASE_URL . "/rest/v1/users";
    
    $data = array(
        "name"       => $name,
        "email"      => $email,
        "role"       => $role,
        "department" => $department,
        "password"   => $hashed_password,
        "reg_no"     => $reg_no // Storing Register Number in DB
    );

    $payload = json_encode($data);

    $ch = curl_init($insert_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ]);

    $insert_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 201 || $http_code == 200) {
        echo "<script>alert('Registration Successful! Please Login.'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Registration Failed! Please try again.'); window.location='register.php';</script>";
    }
}
?>