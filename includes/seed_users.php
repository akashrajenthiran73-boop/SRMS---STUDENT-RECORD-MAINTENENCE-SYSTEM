<?php
/**
 * SRMS Default User Seeder
 * Arignar Anna Government Arts College, Villupuram
 */

require_once __DIR__ . '/db.php';

global $SUPABASE_URL, $SUPABASE_KEY;

if (empty($SUPABASE_URL) || empty($SUPABASE_KEY)) {
    echo "Supabase credentials missing. Skipping seeder.\n";
    exit(0);
}

$defaultUsers = [
    [
        'name'        => 'Super Administrator',
        'email'       => 'admin@aagacvpm.edu.in',
        'role'        => 'Super Admin',
        'department'  => 'Computer Science',
        'password'    => password_hash('admin123', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ],
    [
        'name'        => 'Super Admin User',
        'email'       => 'superadmin@college.edu',
        'role'        => 'Super Admin',
        'department'  => 'Computer Science',
        'password'    => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ],
    [
        'name'        => 'Admin Staff',
        'email'       => 'admin@college.edu',
        'role'        => 'Super Admin',
        'department'  => 'Computer Science',
        'password'    => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ],
    [
        'name'        => 'Dr. HOD',
        'email'       => 'hod@college.edu',
        'role'        => 'HOD',
        'department'  => 'Computer Science',
        'password'    => password_hash('HodPass123!', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ],
    [
        'name'        => 'Prof. Faculty',
        'email'       => 'faculty@college.edu',
        'role'        => 'Faculty',
        'department'  => 'Computer Science',
        'password'    => password_hash('FacultyPass123!', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ],
    [
        'name'        => 'Alice Student',
        'email'       => 'student@college.edu',
        'role'        => 'Student',
        'department'  => 'Computer Science',
        'reg_no'      => '23CS101',
        'password'    => password_hash('StudentPass123!', PASSWORD_BCRYPT),
        'status'      => 'Active'
    ]
];

foreach ($defaultUsers as $u) {
    // Check if user already exists
    $existing = supabase_get('users', '?email=eq.' . urlencode($u['email']) . '&select=id,email');
    if (!empty($existing)) {
        echo "User already exists: " . $u['email'] . "\n";
        continue;
    }

    $ch = curl_init($SUPABASE_URL . '/rest/v1/users');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($u));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Seeded " . $u['email'] . " [HTTP $http]\n";
}

echo "Database user check completed.\n";
