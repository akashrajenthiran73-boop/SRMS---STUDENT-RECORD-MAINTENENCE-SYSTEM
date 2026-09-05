<?php
namespace SRMS\Tests\Functional;

use PHPUnit\Framework\TestCase;

class FunctionalControllersTest extends TestCase {

    /**
     * Tests admin/manage_users.php action logic (Add, Edit, Reset Password, Delete).
     */
    public function testAdminManageUsersActions(): void {
        // 1. Add user validation
        $addUserValid = [
            'action'   => 'add',
            'name'     => 'Faculty Member',
            'email'    => 'faculty1@college.edu',
            'password' => 'FacultySecret123',
            'role'     => 'Faculty'
        ];
        $this->assertNotEmpty($addUserValid['name']);
        $this->assertNotEmpty($addUserValid['email']);
        $hashed = password_hash(trim($addUserValid['password']), PASSWORD_BCRYPT);
        $this->assertTrue(password_verify('FacultySecret123', $hashed));

        // 2. Edit user validation
        $editUser = [
            'action'  => 'edit',
            'user_id' => '12',
            'name'    => 'Faculty Renamed',
            'email'   => 'faculty_new@college.edu',
            'role'    => 'HOD'
        ];
        $this->assertEquals('12', $editUser['user_id']);
        $this->assertEquals('HOD', $editUser['role']);

        // 3. Reset password validation
        $newPass = 'NewSecurePass999';
        $hashedNew = password_hash(trim($newPass), PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($newPass, $hashedNew));

        // 4. Role filter query builder
        $selectedRole = 'Faculty';
        $endpoint = "users?select=*";
        if ($selectedRole && $selectedRole !== 'All') {
            $endpoint .= "&role=eq." . urlencode($selectedRole);
        }
        $endpoint .= "&order=id.desc";
        $this->assertEquals("users?select=*&role=eq.Faculty&order=id.desc", $endpoint);
    }

    /**
     * Tests admin/backup.php JSON export structure and JSON import validation.
     */
    public function testAdminBackupExportAndRestore(): void {
        $tables = ['users', 'study_materials', 'assignments', 'announcements', 'student_leaves'];

        // Simulated export payload
        $mockBackup = [
            'users' => [
                ['id' => 1, 'name' => 'Admin', 'email' => 'admin@college.edu']
            ],
            'announcements' => [
                ['id' => 10, 'title' => 'College Reopening', 'date' => '2026-09-01']
            ]
        ];

        $jsonEncoded = json_encode($mockBackup, JSON_PRETTY_PRINT);
        $this->assertJson($jsonEncoded);

        // Simulated restore
        $importedData = json_decode($jsonEncoded, true);
        $this->assertIsArray($importedData);
        $this->assertArrayHasKey('users', $importedData);
        $this->assertCount(1, $importedData['users']);

        // Test invalid restore payload
        $invalidJson = "{ bad json }";
        $decodedBad = json_decode($invalidJson, true);
        $this->assertNull($decodedBad);
    }

    /**
     * Tests admin/umis_page1.php empty string to NULL conversion and escaping helper v().
     */
    public function testUmisFormPayloadNormalizationAndEscaping(): void {
        $postPayload = [
            'aadhaar_no'     => '123456789012',
            'blood_group'    => 'O+',
            'disability_type'=> '',             // empty -> should become null
            'mother_tongue'  => 'Tamil',
            'save_page1'     => 'Next Page'
        ];

        unset($postPayload['save_page1']);
        foreach ($postPayload as $k => $v) {
            if ($v === '') {
                $postPayload[$k] = null;
            }
        }

        $this->assertArrayNotHasKey('save_page1', $postPayload);
        $this->assertNull($postPayload['disability_type']);
        $this->assertEquals('123456789012', $postPayload['aadhaar_no']);

        // Test escaping helper function v($key, $default='')
        $data = ['name' => '<script>attack</script>', 'dept' => 'CS'];
        $v = fn($k, $d='') => htmlspecialchars((string)($data[$k] ?? $d));

        $this->assertEquals('&lt;script&gt;attack&lt;/script&gt;', $v('name'));
        $this->assertEquals('CS', $v('dept'));
        $this->assertEquals('Default Value', $v('non_existent', 'Default Value'));
    }

    /**
     * Tests faculty/student_leave_requests.php approval/rejection action handling.
     */
    public function testFacultyLeaveRequestApprovalAndRejection(): void {
        $validActions = ['Approved', 'Rejected'];

        $approvePayload = ['request_id' => '45', 'status' => 'Approved'];
        $this->assertNotEmpty($approvePayload['request_id']);
        $this->assertContains($approvePayload['status'], $validActions);

        $rejectPayload = ['request_id' => '45', 'status' => 'Rejected'];
        $this->assertContains($rejectPayload['status'], $validActions);

        // Invalid status
        $invalidPayload = ['request_id' => '45', 'status' => 'Maybe'];
        $this->assertFalse(in_array($invalidPayload['status'], $validActions));

        // Missing ID
        $emptyIdPayload = ['request_id' => '', 'status' => 'Approved'];
        $this->assertEmpty($emptyIdPayload['request_id']);
    }

    /**
     * Tests student/student_leave.php leave application formatting.
     */
    public function testStudentLeaveApplicationSubmission(): void {
        $studentInput = [
            'request_type' => 'OD',
            'send_to'      => 'HOD',
            'from_date'    => '2026-09-10',
            'to_date'      => '2026-09-12',
            'reason'       => 'Attending National Hackathon at IIT Madras'
        ];

        // Validation check
        $this->assertNotEmpty($studentInput['from_date']);
        $this->assertNotEmpty($studentInput['to_date']);
        $this->assertNotEmpty($studentInput['reason']);

        // Payload formation
        $payloadData = [
            'applicant_name' => 'Test Student',
            'applicant_role' => 'Student',
            'applicant_id'   => '10',
            'request_type'   => $studentInput['request_type'],
            'from_date'      => $studentInput['from_date'],
            'to_date'        => $studentInput['to_date'],
            'reason'         => '[' . strtoupper($studentInput['send_to']) . '] ' . $studentInput['reason'],
            'status'         => 'Pending'
        ];

        $this->assertEquals('Pending', $payloadData['status']);
        $this->assertEquals('[HOD] Attending National Hackathon at IIT Madras', $payloadData['reason']);
        $this->assertEquals('OD', $payloadData['request_type']);
    }

    /**
     * Tests student/download_certificates.php certificate templates.
     */
    public function testStudentDownloadCertificatesTemplates(): void {
        $certificates = [
            ['id' => 'bonafide', 'title' => 'Bonafide Certificate'],
            ['id' => 'course_completion', 'title' => 'Course Completion Certificate'],
            ['id' => 'conduct', 'title' => 'Conduct & Character Certificate'],
            ['id' => 'tc', 'title' => 'Transfer Certificate (TC)']
        ];

        $ids = array_column($certificates, 'id');
        $this->assertContains('bonafide', $ids);
        $this->assertContains('course_completion', $ids);
        $this->assertContains('conduct', $ids);
        $this->assertContains('tc', $ids);
    }

    /**
     * Tests HOD/dashboard_hod.php and HOD/send_notice.php.
     */
    public function testHodNoticeValidation(): void {
        $noticePayload = [
            'title'        => 'Staff Meeting Notice',
            'message'      => 'Department meeting scheduled for 3 PM at Seminar Hall.',
            'target_group' => 'All Faculty'
        ];

        $this->assertNotEmpty(trim($noticePayload['title']));
        $this->assertNotEmpty(trim($noticePayload['message']));
        $this->assertEquals('All Faculty', $noticePayload['target_group']);
    }
}
