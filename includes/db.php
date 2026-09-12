<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

$env = [];
if (file_exists(__DIR__ . "/../.env")) {
    $env = @parse_ini_file(__DIR__ . "/../.env") ?: [];
}

$DEFAULT_SUPABASE_URL = 'https://edwndgdjzjevbgdliuxy.supabase.co';
$DEFAULT_SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImVkd25kZ2RqempldmJnZGxpdXh5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODUyNDg1ODgsImV4cCI6MjEwMDgyNDU4OH0.yvqU6cT-xbbLezB4PaXd3lufrfdzN2OwnVzOO7new_c';

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? $DEFAULT_SUPABASE_URL));
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY') ?: ($_ENV['SUPABASE_ANON_KEY'] ?? $DEFAULT_SUPABASE_KEY));


// Supabase ku curl anupuradhu common function
function supabase_get($table, $query="") {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = $SUPABASE_URL."/rest/v1/".$table.$query;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_KEY", 
            "Authorization: Bearer $SUPABASE_KEY"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function supabase_post($table, $data) {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $url = $SUPABASE_URL."/rest/v1/".$table;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=representation"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
?>