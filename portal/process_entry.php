<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/college_data.php';

// Safe .env loader with fallback to getenv()
$env_path = __DIR__ . '/../.env';
$SUPABASE_URL = '';
$SUPABASE_KEY = '';

if (file_exists($env_path)) {
    $env = @parse_ini_file($env_path) ?: [];
    $SUPABASE_URL = trim($env['SUPABASE_URL'] ?? '');
    $SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? '');
}
if (empty($SUPABASE_URL)) $SUPABASE_URL = trim(getenv('SUPABASE_URL') ?: '');
if (empty($SUPABASE_KEY)) $SUPABASE_KEY = trim(getenv('SUPABASE_ANON_KEY') ?: '');

function callSupabaseApi($url, $key, $method = 'GET', $data = null) {
    if (empty($url) || empty($key)) {
        return [[], 0, "Missing configuration"];
    }
    $ch = curl_init($url);
    $headers = [
        "apikey: $key",
        "Authorization: Bearer $key"
    ];
    if ($method === 'POST' || $method === 'PATCH') {
        $headers[] = "Content-Type: application/json";
        $headers[] = "Prefer: return=representation";
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $headers
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [json_decode($res, true), $http, $res];
}

function safeSupabaseWrite($base_url, $table, $id_field, $id_val, $payload, $key) {
    $clean_base = rtrim($base_url, '/');
    
    // Check if record exists
    $check_url = "$clean_base/rest/v1/$table?$id_field=eq." . urlencode($id_val);
    list($existing, $chk_code) = callSupabaseApi($check_url, $key);
    $exists = (!empty($existing) && is_array($existing));

    $sendRequest = function($p) use ($clean_base, $table, $id_field, $id_val, $exists, $key) {
        if ($exists) {
            $url = "$clean_base/rest/v1/$table?$id_field=eq." . urlencode($id_val);
            return callSupabaseApi($url, $key, 'PATCH', $p);
        } else {
            $url = "$clean_base/rest/v1/$table";
            return callSupabaseApi($url, $key, 'POST', $p);
        }
    };

    list($res, $code, $raw) = $sendRequest($payload);

    // If 409 Unique Constraint conflict (e.g. duplicate aadhaar_no, roll_no, or exam_reg_no), auto-patch existing record
    if ($code == 409 && is_string($raw) && preg_match('/Key \(([^)]+)\)=\(([^)]+)\) already exists/', $raw, $uk_match)) {
        $conflict_field = $uk_match[1];
        $conflict_val = $uk_match[2];
        $conflict_url = "$clean_base/rest/v1/$table?$conflict_field=eq." . urlencode($conflict_val);
        list($res, $code, $raw) = callSupabaseApi($conflict_url, $key, 'PATCH', $payload);
    }

    // If schema cache missing column error (HTTP 400), dynamically strip unknown columns and retry
    $retries = 0;
    while ($code == 400 && is_string($raw) && strpos($raw, 'Could not find the') !== false && $retries < 15) {
        $retries++;
        if (preg_match("/'([^']+)' column of '$table'/", $raw, $matches)) {
            $col_name = $matches[1];
            unset($payload[$col_name]);
        }
        list($res, $code, $raw) = $sendRequest($payload);
    }

    return [$code >= 200 && $code < 300, $code, $raw];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: student_entry.php");
    exit;
}

// 1. Mandatory Fields Validation
$errors = [];
$name = trim($_POST['name'] ?? '');
$roll_no = strtoupper(trim($_POST['roll_no'] ?? ''));
$exam_reg_no = strtoupper(trim($_POST['exam_reg_no'] ?? ($_POST['reg_no'] ?? '')));
$reg_no = $exam_reg_no;
$admission_no = trim($_POST['admission_no'] ?? $roll_no);
$email = strtolower(trim($_POST['email'] ?? ''));
$course = strtoupper(trim($_POST['course'] ?? 'CS'));
$ylevel = trim($_POST['academic_year_level'] ?? 'UG_1');

if (empty($name)) $errors[] = "Student Full Name is required.";
if (empty($roll_no)) $errors[] = "Roll Number is required.";
if (empty($exam_reg_no)) $errors[] = "Examination Register Number is required.";
if (empty($admission_no)) $errors[] = "Admission Number is required.";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid Student Email address is required.";
if (empty($course)) $errors[] = "Department / Course selection is required.";

if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: student_entry.php?error=validation");
    exit;
}

// 2. Derive Academic Class and Degree Information
$degree_level = (strpos($ylevel, 'PG') === 0) ? 'PG' : 'UG';
$classes = get_department_classes($course);
$meta = $classes[$ylevel] ?? [
    'label' => "$ylevel $course",
    'short' => 'I B.Sc',
    'sem' => 'Sem 1 & 2'
];
$academic_class = $meta['short'];
$main_subject = trim($_POST['main_subject'] ?? $meta['label']);
$year_of_study = $academic_class;
$academic_year = trim($_POST['academic_year'] ?? '2024-2027');

// 3. Build School Information JSON for UMIS
$school_data = [];
$classes_list = ['12th', '11th', '10th', '9th', '8th', '7th', '6th'];
foreach ($classes_list as $c) {
    if (!empty($_POST["sch_name_$c"])) {
        $st = trim($_POST["sch_type_$c"] ?? 'Govt');
        if ($st === 'Government') $st = 'Govt';
        if ($st === 'Private') $st = 'Unaided';
        $school_data[] = [
            "class" => $c,
            "district" => trim($_POST["sch_dist_$c"] ?? ''),
            "school_name" => trim($_POST["sch_name_$c"] ?? ''),
            "school_type" => $st
        ];
    }
}
$school_json = json_encode($school_data);

function processUploadedPhoto($fileKey, $prefix, $roll_no) {
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fileKey];
    $tmpName = $file['tmp_name'];
    $fileSize = $file['size'];

    // Max 8MB
    if ($fileSize <= 0 || $fileSize > 8 * 1024 * 1024) {
        return null;
    }

    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/students/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0777, true);
    }

    $cleanRoll = preg_replace('/[^A-Za-z0-9]/', '', $roll_no ?: 'stu');
    $fileName = "{$prefix}_{$cleanRoll}_" . time() . "_" . bin2hex(random_bytes(3)) . ".{$ext}";
    $targetPath = $uploadDir . $fileName;

    if (@move_uploaded_file($tmpName, $targetPath)) {
        return "uploads/students/" . $fileName;
    }
    return null;
}

// Handle Photo Uploads (Student & Parents)
$student_photo_url = processUploadedPhoto('student_photo_file', 'stu', $roll_no);
$parents_photo_url = processUploadedPhoto('parents_photo_file', 'parents', $roll_no);

// Fallbacks if not uploaded via file
if (empty($student_photo_url)) {
    $student_photo_url = !empty($_POST['photo_url']) ? trim($_POST['photo_url']) : (!empty($_POST['student_photo']) ? trim($_POST['student_photo']) : 'https://via.placeholder.com/130x160?text=Student+Photo');
}
if (empty($parents_photo_url)) {
    $parents_photo_url = !empty($_POST['parents_photo']) ? trim($_POST['parents_photo']) : null;
}

// 4. Map Comprehensive Payloads for the Three Tables

// Process Semester 1-6 Marks into JSON
$curr_sem_raw = trim($_POST['current_semester'] ?? '1');
if (preg_match('/(\d+)/', $curr_sem_raw, $m)) {
    $current_semester = (int)$m[1];
} else {
    $current_semester = 1;
}
$sem_marks_json = [];

for ($sem = 1; $sem <= 6; $sem++) {
    $marks_data = [];
    if (isset($_POST["sem{$sem}_subject"]) && is_array($_POST["sem{$sem}_subject"])) {
        $subjects = $_POST["sem{$sem}_subject"];
        $ues = $_POST["sem{$sem}_ue"] ?? [];
        $ias = $_POST["sem{$sem}_ia"] ?? [];
        $totals = $_POST["sem{$sem}_total"] ?? [];
        $pfs = $_POST["sem{$sem}_pf"] ?? [];

        for ($i = 0; $i < count($subjects); $i++) {
            $subj = trim($subjects[$i] ?? '');
            if (!empty($subj)) {
                $ue_val = (isset($ues[$i]) && $ues[$i] !== '' && $ues[$i] !== null) ? (float)$ues[$i] : null;
                $ia_val = (isset($ias[$i]) && $ias[$i] !== '' && $ias[$i] !== null) ? (float)$ias[$i] : null;
                $tot_val = (isset($totals[$i]) && $totals[$i] !== '' && $totals[$i] !== null) ? (float)$totals[$i] : (($ue_val !== null || $ia_val !== null) ? (($ue_val ?? 0) + ($ia_val ?? 0)) : null);
                
                $pf_val = trim($pfs[$i] ?? '');
                if (empty($pf_val) && $tot_val !== null) {
                    $pf_val = (($ue_val ?? 0) >= 30 && $tot_val >= 40) ? 'Pass' : 'Fail';
                }

                $marks_data[] = [
                    'subject'   => $subj,
                    'ue'        => $ue_val,
                    'ia'        => $ia_val,
                    'total'     => $tot_val,
                    'pass_fail' => $pf_val
                ];
            }
        }
    }
    $sem_marks_json["sem{$sem}_marks"] = !empty($marks_data) ? json_encode($marks_data) : null;
}

// ==========================================
// A. STUDENTS TABLE PAYLOAD (Photo + 19 Fields + Marks)
// ==========================================
$students_payload = [
    'photo_url' => $student_photo_url,
    'name' => $name,
    'dob' => !empty($_POST['dob']) ? $_POST['dob'] : null,
    'admission_no' => $admission_no,
    'roll_no' => $roll_no,
    'parent_name' => trim($_POST['parent_name'] ?? ($_POST['father_name'] ?? '')),
    'parent_occupation' => trim($_POST['parent_occupation'] ?? ($_POST['father_occupation'] ?? '')),
    'parent_phone' => trim($_POST['parent_phone'] ?? ''),
    'parent_address' => trim($_POST['parent_address'] ?? ($_POST['contact_address'] ?? '')),
    'permanent_address' => trim($_POST['permanent_address'] ?? ''),
    'course' => $course,
    'academic_year_level' => $ylevel,
    'academic_class' => $academic_class,
    'degree_level' => $degree_level,
    'main_subject' => $main_subject,
    'medium' => trim($_POST['medium'] ?? 'English'),
    'ancillary_subjects' => trim($_POST['ancillary_subjects'] ?? ''),
    'exam_reg_no' => $exam_reg_no,
    'reg_no' => $exam_reg_no,
    'current_semester' => $current_semester,
    'sem1_marks' => $sem_marks_json['sem1_marks'] ?? null,
    'sem2_marks' => $sem_marks_json['sem2_marks'] ?? null,
    'sem3_marks' => $sem_marks_json['sem3_marks'] ?? null,
    'sem4_marks' => $sem_marks_json['sem4_marks'] ?? null,
    'sem5_marks' => $sem_marks_json['sem5_marks'] ?? null,
    'sem6_marks' => $sem_marks_json['sem6_marks'] ?? null,
    'date_of_joining' => !empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null,
    'date_of_leaving' => !empty($_POST['date_of_leaving']) ? $_POST['date_of_leaving'] : null,
    'community' => trim($_POST['community'] ?? ''),
    'part1_language' => trim($_POST['part1_language'] ?? 'Tamil'),
    'exam_at_admission' => trim($_POST['exam_at_admission'] ?? ''),
    'last_college' => trim($_POST['last_college'] ?? ''),
    'email' => $email,
    'days_present' => (!empty($_POST['days_present']) && is_numeric($_POST['days_present'])) ? (int)$_POST['days_present'] : null,
    'working_days' => (!empty($_POST['working_days']) && is_numeric($_POST['working_days'])) ? (int)$_POST['working_days'] : null,
    'community_service' => trim($_POST['community_service'] ?? ''),
    'special_activities' => trim($_POST['special_activities'] ?? ''),
    'final_exam_result' => trim($_POST['final_exam_result'] ?? ''),
    'scholarships' => trim($_POST['scholarships'] ?? ($_POST['scholarship_details'] ?? '')),
    'prizes' => trim($_POST['prizes'] ?? ''),
    'class_rank' => (!empty($_POST['class_rank']) && is_numeric($_POST['class_rank'])) ? (int)$_POST['class_rank'] : null,
    'independent_work' => trim($_POST['independent_work'] ?? ''),
    'conduct' => trim($_POST['conduct'] ?? 'Good'),
    'cooperation' => trim($_POST['cooperation'] ?? 'Good'),
    'leadership' => trim($_POST['leadership'] ?? ''),
    'overall_assessment' => trim($_POST['overall_assessment'] ?? 'Good'),
    'hod_name' => trim($_POST['hod_name'] ?? ''),
    'status' => 'pending'
];

foreach ($students_payload as $k => $v) {
    if ($v === '') $students_payload[$k] = null;
}

// ==========================================
// B. BIO_DATA TABLE PAYLOAD (All Fields)
// ==========================================
$raw_accom = trim($_POST['accommodation'] ?? 'Day Scholar');
$accommodation = (strpos($raw_accom, 'Hostel') !== false || $raw_accom === 'Room') ? 'Hostel' : 'Day Scholar';

$raw_travel = trim($_POST['travel_concession'] ?? 'No');
$travel_concession = ($raw_travel === 'Yes' || $raw_travel === 'Bus' || $raw_travel === 'Train') ? 'Yes' : 'No';

$raw_ex = trim($_POST['ex_serviceman'] ?? 'No');
$ex_serviceman = ($raw_ex === 'Yes') ? 'Yes' : 'No';

$raw_att = trim(str_replace('%', '', $_POST['prev_attendance'] ?? ''));
$prev_attendance = (is_numeric($raw_att) && $raw_att !== '') ? (float)$raw_att : null;

$bio_payload = [
    'student_photo' => $student_photo_url,
    'parents_photo' => $parents_photo_url,
    'department' => $course,
    'class' => $academic_class,
    'academic_class' => $academic_class,
    'academic_year_level' => $ylevel,
    'degree_level' => $degree_level,
    'academic_year' => $academic_year,
    'tamil_reg_no' => trim($_POST['tamil_reg_no'] ?? $exam_reg_no),
    'exam_reg_no' => $exam_reg_no,
    'email' => $email,
    'name_ta_en' => trim($_POST['name_ta_en'] ?? $name),
    'parents_name_ta' => trim($_POST['parents_name_ta'] ?? ($_POST['parent_name'] ?? '')),
    'dob' => !empty($_POST['bio_dob']) ? $_POST['bio_dob'] : (!empty($_POST['dob']) ? $_POST['dob'] : null),
    'community' => trim($_POST['bio_community'] ?? ($_POST['community'] ?? '')),
    'caste' => trim($_POST['caste'] ?? ''),
    'emis_no' => trim($_POST['emis_no'] ?? ''),
    'aadhaar_no' => trim($_POST['aadhaar_no'] ?? ''),
    'parent_occupation' => trim($_POST['bio_parent_occupation'] ?? ($_POST['parent_occupation'] ?? ($_POST['father_occupation'] ?? ''))),
    'parent_income' => (!empty($_POST['parent_income']) && is_numeric($_POST['parent_income'])) ? (float)$_POST['parent_income'] : null,
    'accommodation' => $accommodation,
    'travel_concession' => $travel_concession,
    'scholarship_office' => trim($_POST['scholarship_office'] ?? ''),
    'scholarship_details' => trim($_POST['scholarship_details'] ?? ($_POST['scholarships'] ?? '')),
    'prev_attendance' => $prev_attendance,
    'student_phone' => trim($_POST['bio_student_phone'] ?? ($_POST['student_phone'] ?? '')),
    'parent_phone' => trim($_POST['bio_parent_phone'] ?? ($_POST['parent_phone'] ?? '')),
    'contact_address' => trim($_POST['contact_address'] ?? ($_POST['bio_contact_address'] ?? ($_POST['parent_address'] ?? ''))),
    'permanent_address' => trim($_POST['bio_permanent_address'] ?? ($_POST['permanent_address'] ?? '')),
    'ex_serviceman' => $ex_serviceman,
    'disability' => trim($_POST['disability'] ?? ''),
    'achievements' => trim($_POST['achievements'] ?? ''),
    'status' => 'pending'
];

foreach ($bio_payload as $k => $v) {
    if ($v === '') $bio_payload[$k] = null;
}

// ==========================================
// C. UMIS_STUDENTS TABLE PAYLOAD (All Fields)
// ==========================================
$clean_stu_id = 'STU_' . preg_replace('/[^A-Za-z0-9]/', '', $roll_no);
$raw_family_inc = $_POST['family_income'] ?? ($_POST['parent_income'] ?? null);

$umis_payload = [
    'student_id' => $clean_stu_id,
    'college_name' => trim($_POST['college_name'] ?? 'Arignar Anna Government Arts College'),
    'college_code' => trim($_POST['college_code'] ?? 'G105'),
    'college_district' => trim($_POST['college_district'] ?? 'Villupuram'),
    'college_region' => trim($_POST['college_region'] ?? 'Vellore'),
    'emis_id' => trim($_POST['umis_emis_id'] ?? ($_POST['emis_no'] ?? '')),
    'no_emis_reason' => trim($_POST['no_emis_reason'] ?? ''),
    'salutation' => trim($_POST['salutation'] ?? 'Selvan'),
    'student_name_cert' => trim($_POST['student_name_cert'] ?? $name),
    'student_name_aadhaar' => trim($_POST['student_name_aadhaar'] ?? $name),
    'dob' => !empty($_POST['umis_dob']) ? $_POST['umis_dob'] : (!empty($_POST['bio_dob']) ? $_POST['bio_dob'] : (!empty($_POST['dob']) ? $_POST['dob'] : null)),
    'gender' => $_POST['gender'] ?? 'Male',
    'blood_group' => trim($_POST['blood_group'] ?? ''),
    'nationality' => trim($_POST['nationality'] ?? 'Indian'),
    'religion' => trim($_POST['religion'] ?? 'Hindu'),
    'community' => trim($_POST['umis_community'] ?? ($_POST['bio_community'] ?? ($_POST['community'] ?? ''))),
    'caste' => trim($_POST['umis_caste'] ?? ($_POST['caste'] ?? '')),
    'community_cert_no' => trim($_POST['community_cert_no'] ?? ''),
    'aadhaar_no' => trim($_POST['umis_aadhaar_no'] ?? ($_POST['aadhaar_no'] ?? '')),
    'is_first_graduate' => trim($_POST['is_first_graduate'] ?? 'No'),
    'first_graduate_cert_no' => trim($_POST['first_graduate_cert_no'] ?? ''),
    'special_quota' => trim($_POST['special_quota'] ?? 'No'),
    'special_quota_category' => trim($_POST['special_quota_category'] ?? ''),
    'is_differently_abled' => trim($_POST['is_differently_abled'] ?? 'No'),
    'udid_no' => trim($_POST['udid_no'] ?? ''),
    'disability_type' => trim($_POST['disability_type'] ?? ''),
    'disability_percentage' => (!empty($_POST['disability_percentage']) && is_numeric($_POST['disability_percentage'])) ? (int)$_POST['disability_percentage'] : null,
    'mobile' => trim($_POST['umis_mobile'] ?? ($_POST['bio_student_phone'] ?? ($_POST['student_phone'] ?? ''))),
    'email' => trim($_POST['umis_email'] ?? $email),
    'country' => trim($_POST['country'] ?? 'India'),
    'state' => trim($_POST['state'] ?? 'Tamil Nadu'),
    'location_type' => trim($_POST['location_type'] ?? 'Rural'),
    'district' => trim($_POST['district'] ?? 'Villupuram'),
    'taluk' => trim($_POST['taluk'] ?? ''),
    'village' => trim($_POST['village'] ?? ''),
    'block' => trim($_POST['block'] ?? ''),
    'village_panchayat' => trim($_POST['village_panchayat'] ?? ''),
    'pincode' => trim($_POST['pincode'] ?? ''),
    'postal_address' => trim($_POST['postal_address'] ?? ($_POST['permanent_address'] ?? '')),
    'comm_country' => trim($_POST['comm_country'] ?? ($_POST['country'] ?? 'India')),
    'comm_state' => trim($_POST['comm_state'] ?? ($_POST['state'] ?? 'Tamil Nadu')),
    'comm_location_type' => trim($_POST['comm_location_type'] ?? ($_POST['location_type'] ?? 'Rural')),
    'comm_district' => trim($_POST['comm_district'] ?? ($_POST['district'] ?? 'Villupuram')),
    'comm_taluk' => trim($_POST['comm_taluk'] ?? ($_POST['taluk'] ?? '')),
    'comm_village' => trim($_POST['comm_village'] ?? ($_POST['village'] ?? '')),
    'comm_block' => trim($_POST['comm_block'] ?? ($_POST['block'] ?? '')),
    'comm_village_panchayat' => trim($_POST['comm_village_panchayat'] ?? ($_POST['village_panchayat'] ?? '')),
    'comm_pincode' => trim($_POST['comm_pincode'] ?? ($_POST['pincode'] ?? '')),
    'comm_postal_address' => trim($_POST['comm_postal_address'] ?? ($_POST['contact_address'] ?? ($_POST['parent_address'] ?? ($_POST['permanent_address'] ?? '')))),
    'is_orphan' => trim($_POST['is_orphan'] ?? 'No'),
    'father_name' => trim($_POST['father_name'] ?? ($_POST['parent_name'] ?? '')),
    'father_occupation' => trim($_POST['father_occupation'] ?? ($_POST['parent_occupation'] ?? '')),
    'mother_name' => trim($_POST['mother_name'] ?? ''),
    'mother_occupation' => trim($_POST['mother_occupation'] ?? ''),
    'guardian_name' => trim($_POST['guardian_name'] ?? ''),
    'family_income' => (!empty($raw_family_inc) && is_numeric($raw_family_inc)) ? (float)$raw_family_inc : null,
    'income_cert_no' => trim($_POST['income_cert_no'] ?? ''),
    'guardian_mobile' => trim($_POST['guardian_mobile'] ?? ($_POST['parent_phone'] ?? '')),
    'seed_account_no' => trim($_POST['seed_account_no'] ?? ($_POST['acc_no'] ?? '')),
    'seed_mobile' => trim($_POST['seed_mobile'] ?? ($_POST['umis_mobile'] ?? ($_POST['student_phone'] ?? ''))),
    'seed_bank_name' => trim($_POST['seed_bank_name'] ?? ($_POST['bank_name'] ?? '')),
    'seed_status' => trim($_POST['seed_status'] ?? 'Active'),
    'seed_message' => trim($_POST['seed_message'] ?? 'Your Bank Account – Aadhaar Seeding has been done'),
    'acc_no' => trim($_POST['acc_no'] ?? ''),
    'ifsc_code' => strtoupper(trim($_POST['ifsc_code'] ?? '')),
    'bank_name' => trim($_POST['bank_name'] ?? ''),
    'bank_branch' => trim($_POST['bank_branch'] ?? ''),
    'bank_city' => trim($_POST['bank_city'] ?? 'Villupuram'),
    'account_type' => $_POST['account_type'] ?? 'SB',
    'join_year' => trim($_POST['join_year'] ?? '2025–2028'),
    'stream_type' => trim($_POST['stream_type'] ?? 'Regular'),
    'course_type' => trim($_POST['course_type'] ?? 'Regular'),
    'course' => $course,
    'department' => $course,
    'specialization' => trim($_POST['specialization'] ?? $main_subject),
    'medium_of_instruction' => trim($_POST['medium_of_instruction'] ?? ($_POST['medium'] ?? 'English')),
    'mode_of_study' => trim($_POST['mode_of_study'] ?? 'Regular'),
    'date_of_admission' => !empty($_POST['date_of_admission']) ? $_POST['date_of_admission'] : (!empty($_POST['date_of_joining']) ? $_POST['date_of_joining'] : null),
    'type_of_admission' => trim($_POST['type_of_admission'] ?? 'Single Window'),
    'counselling_no' => trim($_POST['counselling_no'] ?? ''),
    'roll_no' => $roll_no,
    'exam_reg_no' => $exam_reg_no,
    'reg_no' => $exam_reg_no,
    'is_lateral_entry' => trim($_POST['is_lateral_entry'] ?? 'No'),
    'is_hosteller' => ($accommodation === 'Hostel' || ($_POST['is_hosteller'] ?? '') === 'Yes') ? 'Yes' : 'No',
    'current_status' => trim($_POST['current_status'] ?? 'Studying in this Institute'),
    'year_of_course_completed' => trim($_POST['year_of_course_completed'] ?? ''),
    'year_of_study' => trim($_POST['year_of_study'] ?? $year_of_study),
    'academic_year_level' => $ylevel,
    'academic_class' => $academic_class,
    'degree_level' => $degree_level,
    'parent_phone' => trim($_POST['umis_parent_phone'] ?? ($_POST['bio_parent_phone'] ?? ($_POST['parent_phone'] ?? ''))),
    'school_info' => $school_json,
    'is_completed' => true,
    'status' => 'pending'
];

foreach ($umis_payload as $k => $v) {
    if ($v === '') $umis_payload[$k] = null;
}

// 5. Execute Writes across all 3 Tables in Supabase
$table_results = [];

// A. Write students table (Match on exam_reg_no)
list($s_ok, $s_code, $s_err) = safeSupabaseWrite($SUPABASE_URL, 'students', 'exam_reg_no', $exam_reg_no, $students_payload, $SUPABASE_KEY);
$table_results['Student Record (students)'] = ['ok' => $s_ok, 'code' => $s_code, 'raw' => $s_err];

// B. Write bio_data table (Match on tamil_reg_no or exam_reg_no)
list($b_ok, $b_code, $b_err) = safeSupabaseWrite($SUPABASE_URL, 'bio_data', 'tamil_reg_no', $exam_reg_no, $bio_payload, $SUPABASE_KEY);
$table_results['Bio Data (bio_data)'] = ['ok' => $b_ok, 'code' => $b_code, 'raw' => $b_err];

// C. Write umis_students table (Match on roll_no or exam_reg_no)
list($u_ok, $u_code, $u_err) = safeSupabaseWrite($SUPABASE_URL, 'umis_students', 'roll_no', $roll_no, $umis_payload, $SUPABASE_KEY);
$table_results['UMIS Details (umis_students)'] = ['ok' => $u_ok, 'code' => $u_code, 'raw' => $u_err];

$all_success = ($s_ok && $b_ok && $u_ok);
$ref_id = 'AAGAC-' . date('Ymd') . '-' . substr(md5($roll_no . $exam_reg_no), 0, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Status - Arignar Anna Government Arts College</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body {
    background: #F8FAFC;
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #0F172A;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 20px;
    box-sizing: border-box;
}
.status-card {
    background: white;
    max-width: 650px;
    width: 100%;
    border-radius: 20px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.08);
    border: 1px solid #E2E8F0;
    padding: 36px 30px;
    text-align: center;
}
.icon-circle {
    width: 76px;
    height: 76px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    margin: 0 auto 20px auto;
}
.icon-success { background: #DCFCE7; color: #16A34A; }
.icon-warning { background: #FEF3C7; color: #D97706; }

h1 { font-size: 22px; font-weight: 800; color: #1E3A8A; margin-bottom: 8px; }
p.lead { color: #64748B; font-size: 14px; margin-bottom: 24px; }

.table-status-list {
    text-align: left;
    background: #F8FAFC;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}
.table-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 8px;
    border-bottom: 1px solid #E2E8F0;
    font-size: 13.5px;
}
.table-status-item:last-child { border-bottom: none; }
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.badge-ok { background: #DCFCE7; color: #166534; }
.badge-err { background: #FEE2E2; color: #991B1B; }

.ref-box {
    background: #EFF6FF;
    border: 1.5px dashed #3B82F6;
    padding: 14px;
    border-radius: 10px;
    margin-bottom: 24px;
}
.ref-box span { font-size: 12px; color: #1E40AF; display: block; margin-bottom: 4px; font-weight: 700; }
.ref-box strong { font-size: 17px; color: #1E3A8A; font-family: monospace; letter-spacing: 1px; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
    border: none;
}
.btn-primary { background: #2563EB; color: white; }
.btn-primary:hover { background: #1D4ED8; }
.btn-outline { background: white; border: 1.5px solid #CBD5E1; color: #475569; margin-left: 10px; }
.btn-outline:hover { background: #F8FAFC; }
</style>
</head>
<body>

<div class="status-card">
    <?php if ($all_success): ?>
        <div class="icon-circle icon-success"><i class="fa-solid fa-circle-check"></i></div>
        <h1>Registration Submitted Successfully!</h1>
        <p class="lead">All details have been submitted and linked across official college records.</p>
    <?php else: ?>
        <div class="icon-circle icon-warning"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <h1>Submission Received with Notices</h1>
        <p class="lead">Some tables saved successfully, while others encountered validation notices.</p>
    <?php endif; ?>

    <div class="ref-box">
        <span>Submission Reference ID</span>
        <strong><?=htmlspecialchars($ref_id)?></strong>
        <div style="font-size: 11.5px; color: #64748B; margin-top: 6px;">
            Student: <b><?=htmlspecialchars($name)?></b> | Reg No: <b><?=htmlspecialchars($exam_reg_no)?></b> | Class: <b><?=htmlspecialchars($academic_class)?></b>
        </div>
    </div>

    <div class="table-status-list">
        <?php foreach ($table_results as $tbl_name => $res): ?>
        <div class="table-status-item">
            <span><strong><?=$tbl_name?></strong></span>
            <?php if ($res['ok']): ?>
                <span class="badge badge-ok"><i class="fa-solid fa-check"></i> Saved (HTTP <?=$res['code']?>)</span>
            <?php else: ?>
                <span class="badge badge-err"><i class="fa-solid fa-xmark"></i> Notice (HTTP <?=$res['code']?>)</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div>
        <a href="student_entry.php" class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Submit Another Student</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print Receipt</button>
    </div>
</div>

</body>
</html>
