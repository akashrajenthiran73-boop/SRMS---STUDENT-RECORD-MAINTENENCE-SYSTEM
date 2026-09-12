<?php
/**
 * College Data Management Helper
 * Arignar Anna Government Arts College, Villupuram
 * SRMS - Student Record Maintenance System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$SUPABASE_URL = trim($env['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?: '');
$SUPABASE_KEY = trim($env['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY') ?: '');

$DATA_STORE_PATH = __DIR__ . '/../data/college_store.json';
if (!defined('COLLEGE_STORE_FILE')) {
    define('COLLEGE_STORE_FILE', __DIR__ . '/../data/college_store.json');
}

/**
 * Initializes default college dataset with realistic records
 */
function init_college_default_store() {
    return [
        'departments' => [
            // Arts and Commerce Departments (5)
            [
                'id' => 'dept_tam',
                'code' => 'TAM',
                'category' => 'Arts & Commerce',
                'name' => 'Department of Tamil',
                'degrees' => 'B.A Tamil Literature, M.A Tamil, Ph.D.',
                'hod_name' => 'Dr. K. Ilangovan, M.A., Ph.D.',
                'hod_email' => 'hod.tam@aagacvpm.edu.in',
                'faculty_count' => 8,
                'student_count' => 170,
                'status' => 'Active',
                'established' => 1968,
                'is_core' => false,
                'description' => 'Classical Tamil literature, linguistics, folk arts, and historic research studies.'
            ],
            [
                'id' => 'dept_eng',
                'code' => 'ENG',
                'category' => 'Arts & Commerce',
                'name' => 'Department of English',
                'degrees' => 'B.A English Literature, M.A English',
                'hod_name' => 'Dr. S. Radhakrishnan, M.A., Ph.D.',
                'hod_email' => 'hod.eng@aagacvpm.edu.in',
                'faculty_count' => 7,
                'student_count' => 160,
                'status' => 'Active',
                'established' => 1970,
                'is_core' => false,
                'description' => 'English literature, linguistics, communication skills, and creative writing programs.'
            ],
            [
                'id' => 'dept_hist',
                'code' => 'HIST',
                'category' => 'Arts & Commerce',
                'name' => 'Department of History',
                'degrees' => 'B.A History, M.A History',
                'hod_name' => 'Dr. M. Senthilkumar, M.A., Ph.D.',
                'hod_email' => 'hod.hist@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 150,
                'status' => 'Active',
                'established' => 1969,
                'is_core' => false,
                'description' => 'Ancient, medieval, modern history, archaeology, and Indian constitutional studies.'
            ],
            [
                'id' => 'dept_eco',
                'code' => 'ECO',
                'category' => 'Arts & Commerce',
                'name' => 'Department of Economics',
                'degrees' => 'B.A Economics, M.A Economics',
                'hod_name' => 'Dr. V. Jayachandran, M.A., Ph.D.',
                'hod_email' => 'hod.eco@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 150,
                'status' => 'Active',
                'established' => 1972,
                'is_core' => false,
                'description' => 'Micro and macro economics, fiscal policy, rural development, and econometrics.'
            ],
            [
                'id' => 'dept_comm',
                'code' => 'COMM',
                'category' => 'Arts & Commerce',
                'name' => 'Department of Commerce',
                'degrees' => 'B.Com (General), M.Com',
                'hod_name' => 'Dr. N. Sundararajan, M.Com., M.Phil., Ph.D.',
                'hod_email' => 'hod.comm@aagacvpm.edu.in',
                'faculty_count' => 9,
                'student_count' => 240,
                'status' => 'Active',
                'established' => 1975,
                'is_core' => false,
                'description' => 'Accounting, financial management, taxation, and corporate governance studies.'
            ],

            // Science and IT Departments (9)
            [
                'id' => 'dept_math',
                'code' => 'MATH',
                'category' => 'Science & IT',
                'name' => 'Department of Mathematics',
                'degrees' => 'B.Sc Mathematics, M.Sc Mathematics',
                'hod_name' => 'Dr. R. Meenakshi, M.Sc., Ph.D.',
                'hod_email' => 'hod.math@aagacvpm.edu.in',
                'faculty_count' => 7,
                'student_count' => 160,
                'status' => 'Active',
                'established' => 1978,
                'is_core' => false,
                'description' => 'Pure and applied mathematics fostering logical thinking and statistical analysis.'
            ],
            [
                'id' => 'dept_phy',
                'code' => 'PHY',
                'category' => 'Science & IT',
                'name' => 'Department of Physics',
                'degrees' => 'B.Sc Physics, M.Sc Physics',
                'hod_name' => 'Dr. M. Selvam, M.Sc., Ph.D.',
                'hod_email' => 'hod.phy@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 140,
                'status' => 'Active',
                'established' => 1982,
                'is_core' => false,
                'description' => 'Equipped with modern optics and electronics laboratories for advanced scientific study.'
            ],
            [
                'id' => 'dept_chem',
                'code' => 'CHEM',
                'category' => 'Science & IT',
                'name' => 'Department of Chemistry',
                'degrees' => 'B.Sc Chemistry, M.Sc Chemistry',
                'hod_name' => 'Dr. P. Annamalai, M.Sc., Ph.D.',
                'hod_email' => 'hod.chem@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 140,
                'status' => 'Active',
                'established' => 1985,
                'is_core' => false,
                'description' => 'Organic, inorganic and analytical chemistry research and practical laboratories.'
            ],
            [
                'id' => 'dept_bot',
                'code' => 'BOT',
                'category' => 'Science & IT',
                'name' => 'Department of Botany',
                'degrees' => 'B.Sc Botany, M.Sc Botany',
                'hod_name' => 'Dr. S. Thangavel, M.Sc., Ph.D.',
                'hod_email' => 'hod.bot@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 130,
                'status' => 'Active',
                'established' => 1986,
                'is_core' => false,
                'description' => 'Plant biology, biotechnology, environmental science, and herbal pharmacology.'
            ],
            [
                'id' => 'dept_zoo',
                'code' => 'ZOO',
                'category' => 'Science & IT',
                'name' => 'Department of Zoology',
                'degrees' => 'B.Sc Zoology, M.Sc Zoology',
                'hod_name' => 'Dr. G. Sivakumar, M.Sc., Ph.D.',
                'hod_email' => 'hod.zoo@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 130,
                'status' => 'Active',
                'established' => 1988,
                'is_core' => false,
                'description' => 'Animal physiology, genetics, immunology, marine biology, and biodiversity studies.'
            ],
            [
                'id' => 'dept_stat',
                'code' => 'STAT',
                'category' => 'Science & IT',
                'name' => 'Department of Statistics',
                'degrees' => 'B.Sc Statistics, M.Sc Statistics',
                'hod_name' => 'Dr. K. Ramasamy, M.Sc., Ph.D.',
                'hod_email' => 'hod.stat@aagacvpm.edu.in',
                'faculty_count' => 5,
                'student_count' => 120,
                'status' => 'Active',
                'established' => 1990,
                'is_core' => false,
                'description' => 'Statistical theory, applied probability, data analytics, and operational research.'
            ],
            [
                'id' => 'dept_cs',
                'code' => 'CS',
                'category' => 'Science & IT',
                'name' => 'Department of Computer Science',
                'degrees' => 'B.Sc Computer Science, M.Sc Computer Science',
                'hod_name' => 'Dr. K. Arulmurugan, M.Sc., M.Phil., Ph.D.',
                'hod_email' => 'hod.cs@aagacvpm.edu.in',
                'faculty_count' => 8,
                'student_count' => 180,
                'status' => 'Active',
                'established' => 1998,
                'is_core' => true,
                'description' => 'The premier computing department offering cutting-edge programs in software development, AI, data structures, and computer applications.'
            ],
            [
                'id' => 'dept_bca',
                'code' => 'BCA',
                'category' => 'Science & IT',
                'name' => 'Department of Computer Applications',
                'degrees' => 'BCA (Bachelor of Computer Applications)',
                'hod_name' => 'Prof. S. Venkatesan, MCA, M.Phil.',
                'hod_email' => 'hod.bca@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 150,
                'status' => 'Active',
                'established' => 2005,
                'is_core' => false,
                'description' => 'Specialized applications and software solutions curriculum catering to industry-ready IT professionals.'
            ],
            [
                'id' => 'dept_it',
                'code' => 'IT',
                'category' => 'Science & IT',
                'name' => 'Department of Information Technology',
                'degrees' => 'B.Sc Information Technology, M.Sc Information Technology',
                'hod_name' => 'Dr. P. Ravichandran, M.Tech., Ph.D.',
                'hod_email' => 'hod.it@aagacvpm.edu.in',
                'faculty_count' => 6,
                'student_count' => 140,
                'status' => 'Active',
                'established' => 2010,
                'is_core' => false,
                'description' => 'Network architecture, cloud infrastructure, cybersecurity, and enterprise systems engineering.'
            ]
        ],
        'circulars' => [
            [
                'id' => 'cir_101',
                'ref_no' => 'AAGAC/CIR/2026/048',
                'title' => 'End Semester University Theory & Practical Examinations Schedule',
                'category' => 'Examination',
                'target_dept' => 'All',
                'priority' => 'High',
                'publish_date' => '2026-09-08',
                'published_by' => 'Controller of Examinations / Principal Office',
                'summary' => 'Timetable and hall ticket issuance for the upcoming November/December 2026 University Examinations.',
                'content' => "All Heads of Departments and students are hereby informed that the End Semester University Examinations (Theory and Practicals) are scheduled to commence shortly. Practical examinations will begin from October 15th, 2026. Hall tickets can be collected from the respective HOD offices after verifying attendance eligibility (minimum 75% mandatory).",
                'attachment' => 'exam_schedule_2026.pdf',
                'status' => 'Published'
            ],
            [
                'id' => 'cir_102',
                'ref_no' => 'AAGAC/CIR/2026/045',
                'title' => 'Post-Matric Scholarship & Higher Education Special Incentive Scheme Renewal',
                'category' => 'Scholarship',
                'target_dept' => 'All',
                'priority' => 'Normal',
                'publish_date' => '2026-09-05',
                'published_by' => 'College Scholarship Section',
                'summary' => 'Last date for submission of renewal applications for BC/MBC/SC/ST state scholarships.',
                'content' => "Eligible students belonging to SC/ST/SCC/BC/MBC/DNC categories are instructed to submit their renewal scholarship forms along with updated income certificate, bank passbook copy, and fee receipts to the college scholarship counter on or before September 25, 2026.",
                'attachment' => 'scholarship_guidelines.pdf',
                'status' => 'Published'
            ],
            [
                'id' => 'cir_103',
                'ref_no' => 'AAGAC/CIR/2026/041',
                'title' => 'CYBERFEST 2026 - State Level Inter-Collegiate Technical Symposium',
                'category' => 'Events',
                'target_dept' => 'CS',
                'priority' => 'Urgent',
                'publish_date' => '2026-09-03',
                'published_by' => 'Department of Computer Science',
                'summary' => 'Annual National/State technical fest hosting Coding, Web Design, Paper Presentation, and Debugging events.',
                'content' => "The Department of Computer Science is proud to organize 'CYBERFEST 2026', a prestigious State-Level Technical Symposium on September 28, 2026. Students from all CS/IT departments across Tamil Nadu colleges will be participating. CS students are requested to coordinate with student coordinators for registration and event preparations.",
                'attachment' => 'cyberfest_brouchure.pdf',
                'status' => 'Published'
            ],
            [
                'id' => 'cir_104',
                'ref_no' => 'AAGAC/CIR/2026/039',
                'title' => 'Declaration of Local Holiday on Account of District Festival',
                'category' => 'Holiday',
                'target_dept' => 'All',
                'priority' => 'Normal',
                'publish_date' => '2026-08-28',
                'published_by' => 'Principal Office',
                'summary' => 'College will remain closed on Friday. Compensatory working day announced.',
                'content' => "As per the notification from the District Collector, Villupuram, the college will remain closed on Friday on account of the annual district car festival. The compensatory working day will be observed on the subsequent second Saturday.",
                'attachment' => '',
                'status' => 'Published'
            ],
            [
                'id' => 'cir_105',
                'ref_no' => 'AAGAC/CIR/2026/035',
                'title' => 'Continuous Internal Assessment (CIA-2) Test Schedule Announced',
                'category' => 'Academic',
                'target_dept' => 'All',
                'priority' => 'High',
                'publish_date' => '2026-08-20',
                'published_by' => 'Academic Council / HOD Committee',
                'summary' => 'CIA-2 tests for all UG and PG classes scheduled from September 18th to 23rd.',
                'content' => "The second Continuous Internal Assessment (CIA-2) tests for all undergraduate and postgraduate students will be conducted from September 18th to 23rd, 2026. Faculty members are requested to submit question papers in the standard format to the exam committee by September 12th.",
                'attachment' => 'cia2_timetable.pdf',
                'status' => 'Published'
            ]
        ],
        'events' => [
            [
                'id' => 'ev_201',
                'title' => 'CYBERFEST 2026 - CS State Level Technical Symposium',
                'category' => 'Symposium',
                'department' => 'CS',
                'event_date' => '2026-09-28',
                'time' => '09:30 AM - 04:30 PM',
                'venue' => 'College Silver Jubilee Auditorium & Lab 1',
                'coordinator' => 'Dr. K. Arulmurugan (HOD CS)',
                'description' => 'Flagship inter-collegiate technical contest with Codeathon, Web Dev Hackathon, AI Project Expo, and Paper Presentations.'
            ],
            [
                'id' => 'ev_202',
                'title' => 'National Seminar on Advances in Applied Mathematics & Data Science',
                'category' => 'Seminar',
                'department' => 'MATH',
                'event_date' => '2026-10-06',
                'time' => '10:00 AM - 03:30 PM',
                'venue' => 'Seminar Hall 2',
                'coordinator' => 'Dr. R. Meenakshi (HOD Math)',
                'description' => 'Keynote addresses by university professors on predictive modelling, cryptography, and stochastic processes.'
            ],
            [
                'id' => 'ev_203',
                'title' => 'Annual Inter-Departmental Sports & Athletic Meet 2026',
                'category' => 'Sports',
                'department' => 'All',
                'event_date' => '2026-10-18',
                'time' => '08:00 AM - 05:00 PM',
                'venue' => 'College Main Sports Ground',
                'coordinator' => 'Physical Education Director',
                'description' => 'Track and field events, Cricket tournament finals, Volleyball, Kabaddi, and Chess championships.'
            ],
            [
                'id' => 'ev_204',
                'title' => '54th College Annual Day & Graduation Honors Celebration',
                'category' => 'Cultural',
                'department' => 'All',
                'event_date' => '2026-11-05',
                'time' => '10:30 AM - 04:00 PM',
                'venue' => 'Silver Jubilee Open Air Theatre',
                'coordinator' => 'Principal & Faculty Committee',
                'description' => 'Distinguished guest address, academic proficiency gold medals distribution, cultural dance & drama programs.'
            ]
        ],
        'grievances' => [
            [
                'id' => 'grv_301',
                'ticket_no' => 'GRV-2026-081',
                'student_id' => 'STU1001',
                'student_name' => 'Karthik Raja S',
                'reg_no' => '22CS104',
                'department' => 'CS',
                'category' => 'Bonafide / Certificates',
                'subject' => 'Urgent Bonafide Certificate required for Passport Application',
                'description' => 'Need Bonafide certificate urgently for passport appointment scheduled next Wednesday. Already submitted fee receipt at office.',
                'status' => 'Resolved',
                'submitted_date' => '2026-09-02',
                'admin_reply' => 'Bonafide certificate has been signed by the Principal and is ready for collection at the HOD CS office.',
                'resolved_date' => '2026-09-04'
            ],
            [
                'id' => 'grv_302',
                'ticket_no' => 'GRV-2026-084',
                'student_id' => 'STU1012',
                'student_name' => 'Priya Dharshini M',
                'reg_no' => '22CS119',
                'department' => 'CS',
                'category' => 'Laboratory / Infrastructure',
                'subject' => 'Python IDE installation in Computer Science Lab 2 systems',
                'description' => 'Systems 12 to 18 in CS Lab 2 require Python 3.11 environment setup for semester lab practicals.',
                'status' => 'In Review',
                'submitted_date' => '2026-09-06',
                'admin_reply' => 'System administrator has been assigned to install and verify Python IDE and Jupyter packages by today afternoon.',
                'resolved_date' => ''
            ],
            [
                'id' => 'grv_303',
                'ticket_no' => 'GRV-2026-089',
                'student_id' => 'STU1024',
                'student_name' => 'Vigneshwaran R',
                'reg_no' => '22CS142',
                'department' => 'CS',
                'category' => 'Examination / Hall Ticket',
                'subject' => 'Name correction in University Exam Application portal',
                'description' => 'Initial letter in my father name was misspelled in the University registration form. Requesting correction before hall ticket release.',
                'status' => 'Pending',
                'submitted_date' => '2026-09-09',
                'admin_reply' => '',
                'resolved_date' => ''
            ]
        ]
    ];
}

/**
 * Reads data store from file with automatic fallback and initialization
 */
function read_college_store() {
    $filePath = defined('COLLEGE_STORE_FILE') ? COLLEGE_STORE_FILE : (__DIR__ . '/../data/college_store.json');
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    if (!file_exists($filePath)) {
        $data = init_college_default_store();
        @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $data;
    }

    $content = @file_get_contents($filePath);
    $data = json_decode($content, true);
    if (!is_array($data) || empty($data['departments'])) {
        $data = init_college_default_store();
        @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    return $data;
}

/**
 * Saves data store to persistent JSON file
 */
function write_college_store(array $data): bool {
    $filePath = defined('COLLEGE_STORE_FILE') ? COLLEGE_STORE_FILE : (__DIR__ . '/../data/college_store.json');
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return (bool) @file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Safe Supabase REST API Caller for College Tables
 */
function supabase_college_call(string $endpoint, string $method = 'GET', array $data = []): array {
    global $SUPABASE_URL, $SUPABASE_KEY;
    if (empty($SUPABASE_URL) || empty($SUPABASE_KEY)) {
        return ['data' => null, 'http' => 0];
    }
    $url = rtrim($SUPABASE_URL, '/') . '/rest/v1/' . ltrim($endpoint, '/');
    $ch = curl_init($url);
    $headers = [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=representation"
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 3
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'PATCH') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($res, true);
    return ['data' => $json, 'http' => $http];
}

// -----------------------------------------------------------------------------
// DEPARTMENTS API
// -----------------------------------------------------------------------------

function get_all_departments(): array {
    // Try fetching from Supabase if rows exist
    $sb = supabase_college_call('college_departments?select=*&order=code.asc');
    if ($sb['http'] >= 200 && $sb['http'] < 300 && !empty($sb['data']) && is_array($sb['data'])) {
        $data = $sb['data'];
        $arts = ['TAM', 'ENG', 'HIST', 'ECO', 'COMM'];
        foreach ($data as &$d) {
            if (empty($d['category'])) {
                $d['category'] = in_array(strtoupper($d['code'] ?? ''), $arts) ? 'Arts & Commerce' : 'Science & IT';
            }
        }
        unset($d);
        return $data;
    }
    $store = read_college_store();
    return $store['departments'] ?? [];
}

function get_department_by_code(string $code): ?array {
    $departments = get_all_departments();
    foreach ($departments as $dept) {
        if (strcasecmp($dept['code'], $code) === 0) {
            return $dept;
        }
    }
    return null;
}

function save_department(array $deptData): bool {
    $store = read_college_store();
    $code = strtoupper(trim($deptData['code'] ?? ''));
    if (empty($code)) return false;

    $updated = false;
    $targetDept = null;
    foreach ($store['departments'] as &$d) {
        if (strcasecmp($d['code'], $code) === 0) {
            $d = array_merge($d, $deptData);
            $d['code'] = $code;
            $updated = true;
            $targetDept = $d;
            break;
        }
    }
    unset($d);

    if (!$updated) {
        $newDept = array_merge([
            'id' => 'dept_' . strtolower($code) . '_' . time(),
            'code' => $code,
            'name' => trim($deptData['name'] ?? 'Department of ' . $code),
            'degrees' => trim($deptData['degrees'] ?? 'B.Sc'),
            'hod_name' => trim($deptData['hod_name'] ?? 'HOD In-Charge'),
            'hod_email' => trim($deptData['hod_email'] ?? 'hod.' . strtolower($code) . '@aagacvpm.edu.in'),
            'faculty_count' => (int)($deptData['faculty_count'] ?? 5),
            'student_count' => (int)($deptData['student_count'] ?? 100),
            'status' => $deptData['status'] ?? 'Active',
            'established' => (int)($deptData['established'] ?? date('Y')),
            'is_core' => false,
            'description' => trim($deptData['description'] ?? '')
        ], $deptData);
        $store['departments'][] = $newDept;
        $targetDept = $newDept;
    }

    // Sync to Supabase college_departments
    if ($targetDept) {
        supabase_college_call('college_departments', 'POST', $targetDept);
    }

    return write_college_store($store);
}

// -----------------------------------------------------------------------------
// CIRCULARS API
// -----------------------------------------------------------------------------

function get_college_circulars(string $dept = 'All', string $category = 'All'): array {
    // Try fetching from Supabase if rows exist
    $sb = supabase_college_call('college_circulars?select=*&order=publish_date.desc');
    if ($sb['http'] >= 200 && $sb['http'] < 300 && !empty($sb['data']) && is_array($sb['data'])) {
        $circulars = $sb['data'];
    } else {
        $store = read_college_store();
        $circulars = $store['circulars'] ?? [];
    }

    if ($dept !== 'All' && !empty($dept)) {
        $circulars = array_filter($circulars, function($c) use ($dept) {
            $t = $c['target_dept'] ?? ($c['dept_code'] ?? 'All');
            return ($t === 'All' || strcasecmp($t, $dept) === 0);
        });
    }

    if ($category !== 'All' && !empty($category)) {
        $circulars = array_filter($circulars, function($c) use ($category) {
            return (strcasecmp($c['category'] ?? '', $category) === 0);
        });
    }

    // Sort by latest publish_date
    usort($circulars, function($a, $b) {
        return strcmp($b['publish_date'] ?? '', $a['publish_date'] ?? '');
    });

    return array_values($circulars);
}

function get_circular_by_id(string $id): ?array {
    $circulars = get_college_circulars();
    foreach ($circulars as $c) {
        if (($c['id'] ?? '') === $id) return $c;
    }
    return null;
}

function save_circular(array $cirData): bool {
    $store = read_college_store();
    $id = $cirData['id'] ?? ('cir_' . time() . '_' . rand(100, 999));
    $targetDept = $cirData['target_dept'] ?? ($cirData['dept_code'] ?? 'All');

    $newCir = [
        'id' => $id,
        'ref_no' => $cirData['ref_no'] ?? ($cirData['reference_no'] ?? ('AAGAC/CIR/' . date('Y') . '/' . rand(100, 999))),
        'reference_no' => $cirData['reference_no'] ?? ($cirData['ref_no'] ?? ('AAGAC/CIR/' . date('Y') . '/' . rand(100, 999))),
        'title' => trim($cirData['title'] ?? 'Official Circular'),
        'category' => $cirData['category'] ?? 'General',
        'target_dept' => $targetDept,
        'dept_code' => $targetDept,
        'priority' => $cirData['priority'] ?? 'Normal',
        'publish_date' => $cirData['publish_date'] ?? date('Y-m-d'),
        'published_by' => $cirData['published_by'] ?? ($cirData['issued_by'] ?? 'Principal Office'),
        'issued_by' => $cirData['issued_by'] ?? ($cirData['published_by'] ?? 'Principal Office'),
        'summary' => trim($cirData['summary'] ?? ''),
        'content' => trim($cirData['content'] ?? ''),
        'attachment' => $cirData['attachment'] ?? '',
        'status' => 'Published'
    ];

    $updated = false;
    foreach ($store['circulars'] as &$c) {
        if ($c['id'] === $id) {
            $c = array_merge($c, $newCir);
            $updated = true;
            break;
        }
    }
    unset($c);

    if (!$updated) {
        array_unshift($store['circulars'], $newCir);
    }

    // Sync to Supabase college_circulars with strict table columns
    $sbCir = [
        'id' => $newCir['id'],
        'ref_no' => $newCir['ref_no'],
        'title' => $newCir['title'],
        'category' => $newCir['category'],
        'target_dept' => $newCir['target_dept'],
        'priority' => $newCir['priority'],
        'publish_date' => $newCir['publish_date'],
        'published_by' => $newCir['published_by'],
        'summary' => $newCir['summary'],
        'content' => $newCir['content'],
        'attachment' => $newCir['attachment'] ?? '',
        'status' => $newCir['status'] ?? 'Published'
    ];
    supabase_college_call('college_circulars', 'POST', $sbCir);

    return write_college_store($store);
}

function delete_circular(string $id): bool {
    $store = read_college_store();
    $store['circulars'] = array_values(array_filter($store['circulars'] ?? [], function($c) use ($id) {
        return $c['id'] !== $id;
    }));
    supabase_college_call('college_circulars?id=eq.' . urlencode($id), 'DELETE');
    return write_college_store($store);
}

// -----------------------------------------------------------------------------
// EVENTS / ACADEMIC CALENDAR API
// -----------------------------------------------------------------------------

function get_college_events(string $dept = 'All'): array {
    $sb = supabase_college_call('college_events?select=*&order=event_date.asc');
    if ($sb['http'] >= 200 && $sb['http'] < 300 && !empty($sb['data']) && is_array($sb['data'])) {
        $events = $sb['data'];
    } else {
        $store = read_college_store();
        $events = $store['events'] ?? [];
    }

    if ($dept !== 'All' && !empty($dept)) {
        $events = array_filter($events, function($e) use ($dept) {
            $d = $e['department'] ?? ($e['dept_code'] ?? 'All');
            return ($d === 'All' || strcasecmp($d, $dept) === 0);
        });
    }

    usort($events, function($a, $b) {
        return strcmp($a['event_date'] ?? '', $b['event_date'] ?? '');
    });

    return array_values($events);
}

function save_event(array $eventData): bool {
    $store = read_college_store();
    $id = $eventData['id'] ?? ('ev_' . time() . '_' . rand(100, 999));
    $cat = $eventData['category'] ?? ($eventData['event_type'] ?? 'Academic');
    $dept = $eventData['department'] ?? ($eventData['dept_code'] ?? 'All');

    $newEvent = [
        'id' => $id,
        'title' => trim($eventData['title'] ?? 'College Event'),
        'category' => $cat,
        'event_type' => $cat,
        'department' => $dept,
        'dept_code' => $dept,
        'event_date' => $eventData['event_date'] ?? date('Y-m-d'),
        'time' => $eventData['time'] ?? '10:00 AM',
        'venue' => $eventData['venue'] ?? 'College Auditorium',
        'coordinator' => $eventData['coordinator'] ?? 'Faculty Coordinator',
        'description' => trim($eventData['description'] ?? '')
    ];

    $updated = false;
    foreach ($store['events'] as &$e) {
        if ($e['id'] === $id) {
            $e = array_merge($e, $newEvent);
            $updated = true;
            break;
        }
    }
    unset($e);

    if (!$updated) {
        $store['events'][] = $newEvent;
    }

    // Sync to Supabase college_events with strict table columns
    $sbEvent = [
        'id' => $newEvent['id'],
        'title' => $newEvent['title'],
        'category' => $newEvent['category'],
        'department' => $newEvent['department'],
        'event_date' => $newEvent['event_date'],
        'time' => $newEvent['time'],
        'venue' => $newEvent['venue'],
        'coordinator' => $newEvent['coordinator'],
        'description' => $newEvent['description']
    ];
    supabase_college_call('college_events', 'POST', $sbEvent);

    return write_college_store($store);
}

function delete_event(string $id): bool {
    $store = read_college_store();
    $store['events'] = array_values(array_filter($store['events'] ?? [], function($e) use ($id) {
        return $e['id'] !== $id;
    }));
    supabase_college_call('college_events?id=eq.' . urlencode($id), 'DELETE');
    return write_college_store($store);
}

// -----------------------------------------------------------------------------
// STUDENT GRIEVANCES API
// -----------------------------------------------------------------------------

function get_student_grievances(string $dept = 'All', ?string $userId = null): array {
    $sb = supabase_college_call('college_grievances?select=*&order=submitted_date.desc');
    if ($sb['http'] >= 200 && $sb['http'] < 300 && !empty($sb['data']) && is_array($sb['data'])) {
        $grievances = $sb['data'];
    } else {
        $store = read_college_store();
        $grievances = $store['grievances'] ?? [];
    }

    if (!empty($userId)) {
        $grievances = array_filter($grievances, function($g) use ($userId) {
            return (($g['student_id'] ?? '') === $userId || ($g['reg_no'] ?? '') === $userId);
        });
    } elseif ($dept !== 'All' && !empty($dept)) {
        $grievances = array_filter($grievances, function($g) use ($dept) {
            return ($g['department'] === 'All' || strcasecmp($g['department'], $dept) === 0);
        });
    }

    usort($grievances, function($a, $b) {
        return strcmp($b['submitted_date'] ?? '', $a['submitted_date'] ?? '');
    });

    return array_values($grievances);
}

function submit_student_grievance(array $data): string {
    $store = read_college_store();
    $ticket = 'GRV-' . date('Y') . '-' . rand(100, 999);
    $id = 'grv_' . time() . '_' . rand(10, 99);

    $entry = [
        'id' => $id,
        'ticket_no' => $ticket,
        'student_id' => $data['student_id'] ?? ($_SESSION['user_id'] ?? 'STU001'),
        'student_name' => $data['student_name'] ?? ($_SESSION['name'] ?? 'Student'),
        'reg_no' => $data['reg_no'] ?? ($_SESSION['reg_no'] ?? 'REG-N/A'),
        'department' => $data['department'] ?? 'CS',
        'category' => $data['category'] ?? 'General',
        'subject' => trim($data['subject'] ?? 'Grievance / Request'),
        'description' => trim($data['description'] ?? ''),
        'status' => 'Pending',
        'submitted_date' => date('Y-m-d'),
        'admin_reply' => '',
        'resolved_date' => ''
    ];

    array_unshift($store['grievances'], $entry);
    write_college_store($store);

    // Sync to Supabase college_grievances with strict table columns
    $sbGrv = [
        'id' => $entry['id'],
        'ticket_no' => $entry['ticket_no'],
        'student_id' => $entry['student_id'],
        'student_name' => $entry['student_name'],
        'reg_no' => $entry['reg_no'],
        'department' => $entry['department'],
        'category' => $entry['category'],
        'subject' => $entry['subject'],
        'description' => $entry['description'],
        'status' => $entry['status'],
        'submitted_date' => $entry['submitted_date'],
        'admin_reply' => $entry['admin_reply'] ?? '',
        'resolved_date' => !empty($entry['resolved_date']) ? $entry['resolved_date'] : null
    ];
    supabase_college_call('college_grievances', 'POST', $sbGrv);

    return $ticket;
}

function update_grievance_status(string $id, string $status, string $adminReply = ''): bool {
    $store = read_college_store();
    $updated = false;

    foreach ($store['grievances'] as &$g) {
        if ($g['id'] === $id) {
            $g['status'] = $status;
            if (!empty($adminReply)) {
                $g['admin_reply'] = $adminReply;
            }
            if ($status === 'Resolved') {
                $g['resolved_date'] = date('Y-m-d');
            }
            $updated = true;
            break;
        }
    }
    unset($g);

    if ($updated) {
        $patch = ['status' => $status];
        if (!empty($adminReply)) $patch['admin_reply'] = $adminReply;
        if ($status === 'Resolved') $patch['resolved_date'] = date('Y-m-d');
        supabase_college_call('college_grievances?id=eq.' . urlencode($id), 'PATCH', $patch);
    }

    return $updated ? write_college_store($store) : false;
}

// -----------------------------------------------------------------------------
// COLLEGE OVERVIEW METRICS
// -----------------------------------------------------------------------------

function get_college_metrics(): array {
    $store = read_college_store();
    $depts = $store['departments'] ?? [];
    $circulars = $store['circulars'] ?? [];
    $events = $store['events'] ?? [];
    $grievances = $store['grievances'] ?? [];

    $totalStudents = 0;
    $totalFaculty = 0;
    foreach ($depts as $d) {
        $totalStudents += (int)($d['student_count'] ?? 0);
        $totalFaculty += (int)($d['faculty_count'] ?? 0);
    }

    $pendingGrievances = count(array_filter($grievances, fn($g) => ($g['status'] ?? '') === 'Pending'));

    return [
        'total_departments' => count($depts),
        'total_students' => $totalStudents > 0 ? $totalStudents : 1460,
        'total_faculty' => $totalFaculty > 0 ? $totalFaculty : 64,
        'total_circulars' => count($circulars),
        'upcoming_events' => count($events),
        'pending_grievances' => $pendingGrievances,
        'active_core_dept' => 'Computer Science (B.Sc CS)'
    ];
}

/**
 * Convenience aliases for operations & tests
 */
function get_college_departments(): array {
    return get_all_departments();
}

function create_college_department(array $deptData): bool {
    return save_department($deptData);
}

function create_college_circular(array $cirData): string {
    if (empty($cirData['id'])) {
        $cirData['id'] = 'cir_' . time() . '_' . rand(100, 999);
    }
    save_circular($cirData);
    return $cirData['id'];
}

function create_college_event(array $eventData): string {
    if (empty($eventData['id'])) {
        $eventData['id'] = 'ev_' . time() . '_' . rand(100, 999);
    }
    save_event($eventData);
    return $eventData['id'];
}

// -----------------------------------------------------------------------------
// MULTI-YEAR & MULTI-CLASS TIMETABLE SYSTEM
// -----------------------------------------------------------------------------

/**
 * Normalizes any department code or full name into standard department code
 */
function normalize_dept_code(string $deptCode): string {
    $dept = trim($deptCode);
    $map = [
        'COMPUTER SCIENCE'       => 'CS',
        'B.SC CS'                => 'CS',
        'B.SC COMPUTER SCIENCE'  => 'CS',
        'BSC CS'                 => 'CS',
        'BSC'                    => 'CS',
        'CS'                     => 'CS',
        'COMPUTER APPLICATIONS'  => 'BCA',
        'BCA'                    => 'BCA',
        'INFORMATION TECHNOLOGY' => 'IT',
        'IT'                     => 'IT',
        'TAMIL'                  => 'TAM',
        'ENGLISH'                => 'ENG',
        'MATHEMATICS'            => 'MATH',
        'PHYSICS'                => 'PHY',
        'CHEMISTRY'              => 'CHEM',
        'COMMERCE'               => 'COMM',
        'ECONOMICS'              => 'ECO',
        'HISTORY'                => 'HIST',
        'BOTANY'                 => 'BOT',
        'ZOOLOGY'                => 'ZOO',
        'STATISTICS'             => 'STAT'
    ];
    $upper = strtoupper($dept);
    return $map[$upper] ?? ($upper ?: 'CS');
}

/**
 * Returns available classes/degrees for a given department
 */
function get_department_classes(string $deptCode): array {
    $code = normalize_dept_code($deptCode);

    switch ($code) {

        // Arts & Commerce Departments (5)
        case 'TAM':
            $classes = [
                'UG_1' => ['label' => 'I B.A Tamil', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.A'],
                'UG_2' => ['label' => 'II B.A Tamil', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.A'],
                'UG_3' => ['label' => 'III B.A Tamil', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.A'],
                'PG_1' => ['label' => 'I M.A Tamil', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.A'],
                'PG_2' => ['label' => 'II M.A Tamil', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.A']
            ];
            break;
        case 'ENG':
            $classes = [
                'UG_1' => ['label' => 'I B.A English', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.A'],
                'UG_2' => ['label' => 'II B.A English', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.A'],
                'UG_3' => ['label' => 'III B.A English', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.A'],
                'PG_1' => ['label' => 'I M.A English', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.A'],
                'PG_2' => ['label' => 'II M.A English', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.A']
            ];
            break;
        case 'HIST':
            $classes = [
                'UG_1' => ['label' => 'I B.A History', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.A'],
                'UG_2' => ['label' => 'II B.A History', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.A'],
                'UG_3' => ['label' => 'III B.A History', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.A'],
                'PG_1' => ['label' => 'I M.A History', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.A'],
                'PG_2' => ['label' => 'II M.A History', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.A']
            ];
            break;
        case 'ECO':
            $classes = [
                'UG_1' => ['label' => 'I B.A Economics', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.A'],
                'UG_2' => ['label' => 'II B.A Economics', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.A'],
                'UG_3' => ['label' => 'III B.A Economics', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.A'],
                'PG_1' => ['label' => 'I M.A Economics', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.A'],
                'PG_2' => ['label' => 'II M.A Economics', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.A']
            ];
            break;
        case 'COMM':
            $classes = [
                'UG_1' => ['label' => 'I B.Com Commerce', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Com'],
                'UG_2' => ['label' => 'II B.Com Commerce', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Com'],
                'UG_3' => ['label' => 'III B.Com Commerce', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Com'],
                'PG_1' => ['label' => 'I M.Com Commerce', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Com'],
                'PG_2' => ['label' => 'II M.Com Commerce', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Com']
            ];
            break;

        // Science & IT Departments (9)
        case 'MATH':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Mathematics', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Mathematics', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Mathematics', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Mathematics', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Mathematics', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'PHY':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Physics', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Physics', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Physics', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Physics', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Physics', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'CHEM':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Chemistry', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Chemistry', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Chemistry', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Chemistry', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Chemistry', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'BOT':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Botany', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Botany', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Botany', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Botany', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Botany', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'ZOO':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Zoology', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Zoology', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Zoology', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Zoology', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Zoology', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'STAT':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Statistics', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Statistics', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Statistics', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Statistics', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Statistics', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'CS':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Computer Science', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Computer Science', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Computer Science', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Computer Science', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Computer Science', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        case 'BCA':
            $classes = [
                'UG_1' => ['label' => 'I BCA (Computer Applications)', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I BCA'],
                'UG_2' => ['label' => 'II BCA (Computer Applications)', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II BCA'],
                'UG_3' => ['label' => 'III BCA (Computer Applications)', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III BCA']
            ];
            break;
        case 'IT':
            $classes = [
                'UG_1' => ['label' => 'I B.Sc Information Technology', 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I B.Sc'],
                'UG_2' => ['label' => 'II B.Sc Information Technology', 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II B.Sc'],
                'UG_3' => ['label' => 'III B.Sc Information Technology', 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => 'III B.Sc'],
                'PG_1' => ['label' => 'I M.Sc Information Technology', 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'I M.Sc'],
                'PG_2' => ['label' => 'II M.Sc Information Technology', 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'II M.Sc']
            ];
            break;
        default:
            $classes = [
                'UG_1' => ['label' => "I $code (1st Year)", 'level' => 'UG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => '1st Year'],
                'UG_2' => ['label' => "II $code (2nd Year)", 'level' => 'UG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => '2nd Year'],
                'UG_3' => ['label' => "III $code (3rd Year)", 'level' => 'UG', 'year' => 3, 'sem' => 'Sem 5 & 6', 'short' => '3rd Year'],
                'PG_1' => ['label' => "I M.$code (PG 1st Year)", 'level' => 'PG', 'year' => 1, 'sem' => 'Sem 1 & 2', 'short' => 'PG 1st Yr'],
                'PG_2' => ['label' => "II M.$code (PG 2nd Year)", 'level' => 'PG', 'year' => 2, 'sem' => 'Sem 3 & 4', 'short' => 'PG 2nd Yr']
            ];
            break;
    }

    foreach ($classes as &$item) {
        $item['degree_level'] = $item['level'];
        $item['year_num'] = $item['year'];
    }
    unset($item);
    return $classes;
}

/**
 * Resolves a student's degree level, academic year, and class details
 */
function get_student_year_info(array $student): array {
    // 1. Check explicit class_code or academic_year_level if present
    $explicitCode = strtoupper(trim($student['class_code'] ?? ($student['academic_year_level'] ?? '')));
    
    // 2. Check academic_class column (e.g. 'III B.Sc', 'II B.A', 'I M.Sc')
    if (empty($explicitCode) && !empty($student['academic_class'])) {
        $ac = strtoupper(trim($student['academic_class']));
        $isPg = (strpos($ac, 'M.') !== false || strpos($ac, 'PG') !== false || strpos($ac, 'M.SC') !== false || strpos($ac, 'M.COM') !== false || strpos($ac, 'M.A') !== false);
        if (strpos($ac, 'III') !== false || strpos($ac, '3') !== false) {
            $explicitCode = $isPg ? 'PG_2' : 'UG_3';
        } elseif (strpos($ac, 'II') !== false || strpos($ac, '2') !== false) {
            $explicitCode = $isPg ? 'PG_2' : 'UG_2';
        } elseif (strpos($ac, 'I') !== false || strpos($ac, '1') !== false) {
            $explicitCode = $isPg ? 'PG_1' : 'UG_1';
        }
    }

    if (in_array($explicitCode, ['UG_1', 'UG_2', 'UG_3', 'PG_1', 'PG_2'])) {
        $level = strpos($explicitCode, 'PG') === 0 ? 'PG' : 'UG';
        $year = (int)substr($explicitCode, 3);
        $classCode = $explicitCode;
    } else {
        // Collect text hints from multiple possible fields across students, bio_data, and umis tables
        $mainSub = strtoupper(trim(($student['main_subject'] ?? '') . ' ' . ($student['class'] ?? '') . ' ' . ($student['year_of_study'] ?? '')));
        $course = strtoupper(trim(($student['course'] ?? '') . ' ' . ($student['department'] ?? '')));
        $roll = strtoupper(trim(($student['roll_no'] ?? '') . ' ' . ($student['reg_no'] ?? '') . ' ' . ($student['tamil_reg_no'] ?? '') . ' ' . ($student['exam_reg_no'] ?? '')));

        // Check roll number batch year prefix (e.g. 24CSC125 -> prefix '24')
        $batchPrefix = '';
        if (preg_match('/^(\d{2})/', $roll, $m)) {
            $batchPrefix = $m[1];
        }

        $level = 'UG';
        $year = 3;
        $classCode = 'UG_3';

        if (strpos($mainSub, 'M.SC') !== false || strpos($mainSub, 'M.COM') !== false || strpos($mainSub, 'M.A') !== false || strpos($course, 'PG') !== false || strpos($mainSub, 'PG') !== false) {
            $level = 'PG';
            if (strpos($mainSub, 'I ') === 0 || strpos($mainSub, '1ST') !== false || strpos($mainSub, 'PG_1') !== false || $batchPrefix === '26' || $batchPrefix === '25') {
                $year = ($batchPrefix === '25') ? 2 : 1;
                $classCode = ($batchPrefix === '25') ? 'PG_2' : 'PG_1';
            } elseif (strpos($mainSub, 'II ') === 0 || strpos($mainSub, '2ND') !== false || strpos($mainSub, 'PG_2') !== false || $batchPrefix === '24') {
                $year = 2;
                $classCode = 'PG_2';
            } else {
                $year = 1;
                $classCode = 'PG_1';
            }
        } else {
            $level = 'UG';
            if (strpos($mainSub, 'I ') === 0 || strpos($mainSub, '1ST') !== false || strpos($mainSub, 'UG_1') !== false || $batchPrefix === '26') {
                $year = 1;
                $classCode = 'UG_1';
            } elseif (strpos($mainSub, 'II ') === 0 || strpos($mainSub, '2ND') !== false || strpos($mainSub, 'UG_2') !== false || $batchPrefix === '25') {
                $year = 2;
                $classCode = 'UG_2';
            } else {
                $year = 3;
                $classCode = 'UG_3';
            }
        }
    }

    $dept = strtoupper(trim($student['course'] ?? ($student['department'] ?? '')));
    if (empty($dept) || $dept === 'BSC') $dept = 'CS';
    // Match department code if full name was passed (e.g. "Computer Science" -> "CS")
    if (strpos($dept, 'COMPUTER') !== false && strpos($dept, 'APP') === false) $dept = 'CS';
    elseif (strpos($dept, 'APP') !== false || strpos($dept, 'BCA') !== false) $dept = 'BCA';
    elseif (strpos($dept, 'INFO') !== false || strpos($dept, 'IT') !== false) $dept = 'IT';
    elseif (strpos($dept, 'MATH') !== false) $dept = 'MATH';
    elseif (strpos($dept, 'PHYS') !== false || strpos($dept, 'PHY') !== false) $dept = 'PHY';
    elseif (strpos($dept, 'CHEM') !== false) $dept = 'CHEM';
    elseif (strpos($dept, 'BOT') !== false) $dept = 'BOT';
    elseif (strpos($dept, 'ZOO') !== false) $dept = 'ZOO';
    elseif (strpos($dept, 'STAT') !== false) $dept = 'STAT';
    elseif (strpos($dept, 'COMM') !== false) $dept = 'COMM';
    elseif (strpos($dept, 'ECON') !== false || strpos($dept, 'ECO') !== false) $dept = 'ECO';
    elseif (strpos($dept, 'HIST') !== false) $dept = 'HIST';
    elseif (strpos($dept, 'ENG') !== false) $dept = 'ENG';
    elseif (strpos($dept, 'TAM') !== false) $dept = 'TAM';

    $classes = get_department_classes($dept);
    $meta = $classes[$classCode] ?? [
        'label' => "$level Year $year",
        'level' => $level,
        'year' => $year,
        'sem' => "Year $year",
        'short' => "$level $year"
    ];

    return [
        'level' => $level,
        'degree_level' => $level,
        'year' => $year,
        'year_num' => $year,
        'class_code' => $classCode,
        'label' => $meta['label'],
        'short' => $meta['short'],
        'sem' => $meta['sem'],
        'filter_tag' => "{$level}_{$year}",
        'dept_code' => $dept
    ];
}

/**
 * Returns realistic default timetable schedule for any department & class
 */
function get_default_class_timetable(string $deptCode, string $classCode): array {
    $dept = strtoupper($deptCode);

    if ($dept === 'CS' || $dept === 'IT' || $dept === 'BCA') {
        if ($classCode === 'UG_1') {
            return [
                'I'   => [['sub' => 'C-PROG(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'DIG-ELEC(SD)', 'span' => 1, 'hour' => 2], ['sub' => 'TAMIL(KRM)', 'span' => 1, 'hour' => 3], ['sub' => 'ENG(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'MATH-I(GKM)', 'span' => 1, 'hour' => 5]],
                'II'  => [['sub' => 'ENG(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'C-PROG(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'C-LAB(RM)', 'span' => 2, 'hour' => 3], ['sub' => 'TAMIL(KRM)', 'span' => 1, 'hour' => 5]],
                'III' => [['sub' => 'DIG-ELEC(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'MATH-I(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'C-PROG(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'ENG(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'OFFICE-AUTO', 'span' => 1, 'hour' => 5]],
                'IV'  => [['sub' => 'TAMIL(KRM)', 'span' => 1, 'hour' => 1], ['sub' => 'C-PROG(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'DIG-ELEC(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'C-LAB(RM)', 'span' => 2, 'hour' => 4]],
                'V'   => [['sub' => 'MATH-I(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'DIG-ELEC(SD)', 'span' => 1, 'hour' => 2], ['sub' => 'ENG(ACA)', 'span' => 1, 'hour' => 3], ['sub' => 'C-PROG(RM)', 'span' => 1, 'hour' => 4], ['sub' => 'VE(SD)', 'span' => 1, 'hour' => 5]],
                'VI'  => [['sub' => 'OFFICE-LAB', 'span' => 2, 'hour' => 1], ['sub' => 'TAMIL(KRM)', 'span' => 1, 'hour' => 3], ['sub' => 'MATH-I(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'LIBRARY', 'span' => 1, 'hour' => 5]]
            ];
        } elseif ($classCode === 'UG_2') {
            return [
                'I'   => [['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'DS(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'WEB-TECH(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'STAT-I(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'TAMIL(KRM)', 'span' => 1, 'hour' => 5]],
                'II'  => [['sub' => 'DS(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'JAVA-LAB(GKM)', 'span' => 2, 'hour' => 3], ['sub' => 'STAT-I(ACA)', 'span' => 1, 'hour' => 5]],
                'III' => [['sub' => 'WEB-TECH(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'DS(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 3], ['sub' => 'ENG-COMM(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'E-COMMERCE', 'span' => 1, 'hour' => 5]],
                'IV'  => [['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'DS-LAB(RM)', 'span' => 2, 'hour' => 2], ['sub' => 'WEB-TECH(SD)', 'span' => 1, 'hour' => 4], ['sub' => 'NME(SD)', 'span' => 1, 'hour' => 5]],
                'V'   => [['sub' => 'STAT-I(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'DS(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'WEB-TECH(SD)', 'span' => 1, 'hour' => 4], ['sub' => 'E-COMMERCE', 'span' => 1, 'hour' => 5]],
                'VI'  => [['sub' => 'WEB-LAB(SD)', 'span' => 2, 'hour' => 1], ['sub' => 'DS(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'JAVA(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'SEMINAR', 'span' => 1, 'hour' => 5]]
            ];
        } elseif ($classCode === 'PG_1') {
            return [
                'I'   => [['sub' => 'ADV-ALGO(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'CLOUD-COMP(ACA)', 'span' => 1, 'hour' => 2], ['sub' => 'DIST-SYS(GKM)', 'span' => 1, 'hour' => 3], ['sub' => 'RESEARCH-METH', 'span' => 1, 'hour' => 4], ['sub' => 'SEMINAR(SD)', 'span' => 1, 'hour' => 5]],
                'II'  => [['sub' => 'DIST-SYS(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'ADV-ALGO(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'CLOUD-LAB(ACA)', 'span' => 2, 'hour' => 3], ['sub' => 'ELECTIVE-I', 'span' => 1, 'hour' => 5]],
                'III' => [['sub' => 'CLOUD-COMP(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'DIST-SYS(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'ADV-ALGO(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'RESEARCH-METH', 'span' => 1, 'hour' => 4], ['sub' => 'AI-INTRO(SD)', 'span' => 1, 'hour' => 5]],
                'IV'  => [['sub' => 'ADV-ALGO-LAB(RM)', 'span' => 2, 'hour' => 1], ['sub' => 'CLOUD-COMP(ACA)', 'span' => 1, 'hour' => 3], ['sub' => 'DIST-SYS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'LIBRARY', 'span' => 1, 'hour' => 5]],
                'V'   => [['sub' => 'AI-INTRO(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'ADV-ALGO(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'CLOUD-COMP(ACA)', 'span' => 1, 'hour' => 3], ['sub' => 'ELECTIVE-I', 'span' => 1, 'hour' => 4], ['sub' => 'MENTORING', 'span' => 1, 'hour' => 5]],
                'VI'  => [['sub' => 'DIST-LAB(GKM)', 'span' => 2, 'hour' => 1], ['sub' => 'ADV-ALGO(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'CLOUD-COMP(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'COLLOQUIUM', 'span' => 1, 'hour' => 5]]
            ];
        } elseif ($classCode === 'PG_2') {
            return [
                'I'   => [['sub' => 'ML-TECH(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'DEEP-LEARN(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'CYBER-SEC(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'BIG-DATA(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'PROJECT-VIVA', 'span' => 1, 'hour' => 5]],
                'II'  => [['sub' => 'DEEP-LEARN(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'ML-TECH(GKM)', 'span' => 2, 'hour' => 2], ['sub' => 'AI-LAB(GKM)', 'span' => 2, 'hour' => 4]],
                'III' => [['sub' => 'CYBER-SEC(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'BIG-DATA(ACA)', 'span' => 1, 'hour' => 2], ['sub' => 'ML-TECH(GKM)', 'span' => 1, 'hour' => 3], ['sub' => 'DEEP-LEARN(RM)', 'span' => 1, 'hour' => 4], ['sub' => 'PROJECT-WORK', 'span' => 1, 'hour' => 5]],
                'IV'  => [['sub' => 'BIG-DATA-LAB(ACA)', 'span' => 2, 'hour' => 1], ['sub' => 'CYBER-SEC(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'PROJECT-WORK', 'span' => 2, 'hour' => 4]],
                'V'   => [['sub' => 'ML-TECH(GKM)', 'span' => 1, 'hour' => 1], ['sub' => 'CYBER-SEC(SD)', 'span' => 1, 'hour' => 2], ['sub' => 'DEEP-LEARN(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'BIG-DATA(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'JOURNAL-CLUB', 'span' => 1, 'hour' => 5]],
                'VI'  => [['sub' => 'DISSERTATION', 'span' => 3, 'hour' => 1], ['sub' => 'ML-TECH(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'DEEP-LEARN(RM)', 'span' => 1, 'hour' => 5]]
            ];
        }
    }

    // Default canonical schedule (CS_UG_3 and standard fallback)
    return [
        'I'   => [['sub' => 'SE(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 2], ['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 3], ['sub' => 'PROJ-VIVA(ACA)', 'span' => 1, 'hour' => 4], ['sub' => 'V.Edu(SD)', 'span' => 1, 'hour' => 5]],
        'II'  => [['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(ACA)', 'span' => 1, 'hour' => 2], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 3], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 4], ['sub' => 'PROJ-VIVA(RM)', 'span' => 1, 'hour' => 5]],
        'III' => [['sub' => 'OS(ACA)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(SD)', 'span' => 1, 'hour' => 2], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'Tnskill-SALESFORCE', 'span' => 1, 'hour' => 5]],
        'IV'  => [['sub' => 'SE(RM)', 'span' => 1, 'hour' => 1], ['sub' => 'PROJ-VIVA(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS LAB(GKM)', 'span' => 2, 'hour' => 4]],
        'V'   => [['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 1], ['sub' => 'SE(RM)', 'span' => 1, 'hour' => 2], ['sub' => 'Tnskill-SALESFORCE', 'span' => 1, 'hour' => 3], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'V.Edu(SD)', 'span' => 1, 'hour' => 5]],
        'VI'  => [['sub' => 'DBMS LAB(GKM)', 'span' => 3, 'hour' => 1], ['sub' => 'DBMS(GKM)', 'span' => 1, 'hour' => 4], ['sub' => 'DM & W(SD)', 'span' => 1, 'hour' => 5]]
    ];
}

/**
 * Normalizes slots for a single day order to ensure exactly 5 hours are represented.
 * Missing hours are padded with clickable placeholder slots ('—').
 * Lab sessions with span > 1 are appropriately accounted for so total span is always 5.
 */
function normalize_timetable_day_slots(array $slots): array {
    $byHour = [];
    foreach ($slots as $s) {
        $h = (int)($s['hour'] ?? 1);
        if ($h >= 1 && $h <= 5) {
            $sub = trim((string)($s['sub'] ?? '—'));
            if ($sub === '' || $sub === '-') $sub = '—';
            $byHour[$h] = [
                'sub'  => $sub,
                'span' => max(1, (int)($s['span'] ?? 1)),
                'hour' => $h
            ];
        }
    }

    $normalized = [];
    $h = 1;
    while ($h <= 5) {
        if (isset($byHour[$h])) {
            $slot = $byHour[$h];
            $maxPossibleSpan = 6 - $h;
            if ($slot['span'] > $maxPossibleSpan) {
                $slot['span'] = $maxPossibleSpan;
            }
            $normalized[] = $slot;
            $h += $slot['span'];
        } else {
            $normalized[] = [
                'sub'  => '—',
                'span' => 1,
                'hour' => $h
            ];
            $h += 1;
        }
    }

    return $normalized;
}

/**
 * Fetches timetable for a specific department and class.
 * Guarantees a complete 5-period schedule with no missing cells or whitespace gaps.
 */
function get_class_timetable(string $deptCode, string $classCode = 'UG_3'): array {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $dept = normalize_dept_code($deptCode);
    $class = trim($classCode);
    if (empty($class)) $class = 'UG_3';
    $key = "{$dept}_{$class}";

    $store = read_college_store();
    $timetables = $store['timetables'] ?? [];

    $schedule = get_default_class_timetable($dept, $class);

    // If local store has customized timetable for this key, overlay it
    if (isset($timetables[$key]) && is_array($timetables[$key]) && !empty($timetables[$key])) {
        foreach ($timetables[$key] as $d => $slots) {
            if (isset($schedule[$d]) && is_array($slots)) {
                $schedule[$d] = $slots;
            }
        }
    }

    // Check Supabase real-time timetable table for UG_3
    if ($dept === 'CS' && $class === 'UG_3' && !empty($SUPABASE_URL) && !empty($SUPABASE_KEY)) {
        $fetch_url = rtrim($SUPABASE_URL, '/') . "/rest/v1/timetable?class_code=eq.UG_3&order=day_order,hour_slot";
        $ch = curl_init($fetch_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY"
        ]);
        $db_response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $db_data = json_decode($db_response, true);
        if ($http >= 200 && $http < 300 && is_array($db_data) && !empty($db_data)) {
            $db_by_day = [];
            foreach ($db_data as $row) {
                $d = $row['day_order'];
                if (!isset($db_by_day[$d])) $db_by_day[$d] = [];
                $db_by_day[$d][] = [
                    'sub' => $row['subject_code'],
                    'span' => (int)($row['colspan'] ?? 1),
                    'hour' => (int)$row['hour_slot']
                ];
            }
            foreach ($db_by_day as $d => $slots) {
                if (isset($schedule[$d]) && !empty($slots)) {
                    $hourMap = [];
                    foreach ($schedule[$d] as $s) {
                        $hourMap[$s['hour']] = $s;
                    }
                    foreach ($slots as $s) {
                        $hourMap[$s['hour']] = $s;
                    }
                    $schedule[$d] = array_values($hourMap);
                }
            }
        }
    }

    // Normalize all 6 day orders so every day has exactly 5 total periods (no broken whitespace gaps)
    foreach (['I', 'II', 'III', 'IV', 'V', 'VI'] as $d) {
        $schedule[$d] = normalize_timetable_day_slots($schedule[$d] ?? []);
    }

    if (!isset($store['timetables'])) $store['timetables'] = [];
    $store['timetables'][$key] = $schedule;
    write_college_store($store);
    return $schedule;
}

/**
 * Saves an edited slot for a specific department and class
 */
function save_class_timetable_slot(string $deptCode, string $classCode, string $day, int $hour, string $subject, int $span = 1): bool {
    global $SUPABASE_URL, $SUPABASE_KEY;
    $dept = normalize_dept_code($deptCode);
    $class = trim($classCode);
    if (empty($class)) $class = 'UG_3';
    $key = "{$dept}_{$class}";

    $schedule = get_class_timetable($dept, $class);
    if (!isset($schedule[$day])) {
        $schedule[$day] = [];
    }

    $sub = trim($subject);
    if ($sub === '' || $sub === '-') {
        $sub = '—';
    }

    $found = false;
    foreach ($schedule[$day] as &$slot) {
        if ((int)$slot['hour'] === (int)$hour) {
            $slot['sub'] = $sub;
            $slot['span'] = max(1, (int)$span);
            $found = true;
            break;
        }
    }
    unset($slot);

    if (!$found) {
        $schedule[$day][] = [
            'sub' => $sub,
            'span' => max(1, (int)$span),
            'hour' => (int)$hour
        ];
    }

    // Normalize all 6 days so layout remains perfectly intact
    foreach (['I', 'II', 'III', 'IV', 'V', 'VI'] as $d) {
        $schedule[$d] = normalize_timetable_day_slots($schedule[$d] ?? []);
    }

    $store = read_college_store();
    if (!isset($store['timetables'])) {
        $store['timetables'] = [];
    }
    $store['timetables'][$key] = $schedule;
    $saved = write_college_store($store);

    // Synchronize to Supabase timetable table for UG_3
    // (Supabase timetable table is keyed by day_order, hour_slot)
    if (!empty($SUPABASE_URL) && !empty($SUPABASE_KEY) && $class === 'UG_3' && $dept === 'CS') {
        $url = rtrim($SUPABASE_URL, '/') . "/rest/v1/timetable?on_conflict=day_order,hour_slot";
        $payload = json_encode([
            'day_order'    => $day,
            'hour_slot'    => (int)$hour,
            'subject_code' => $sub,
            'colspan'      => max(1, (int)$span),
            'department'   => 'B.Sc CS',
            'class_code'   => $class,
            'updated_at'   => date('c')
        ]);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: $SUPABASE_KEY",
            "Authorization: Bearer $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: resolution=merge-duplicates"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    return $saved;
}
?>
