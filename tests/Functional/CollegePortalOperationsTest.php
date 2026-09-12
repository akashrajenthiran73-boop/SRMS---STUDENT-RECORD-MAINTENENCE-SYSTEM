<?php
namespace SRMS\Tests\Functional;

use PHPUnit\Framework\TestCase;

use function get_college_departments;
use function create_college_department;
use function create_college_circular;
use function get_college_circulars;
use function create_college_event;
use function get_college_events;
use function submit_student_grievance;
use function get_student_grievances;
use function update_grievance_status;
use function get_department_classes;
use function get_student_year_info;
use function get_class_timetable;
use function save_class_timetable_slot;

require_once __DIR__ . '/../../includes/college_data.php';

class CollegePortalOperationsTest extends TestCase {
    
    public function testCollegeDepartmentsIncludeComputerScience(): void {
        $departments = get_college_departments();
        $this->assertNotEmpty($departments);

        $cs = null;
        foreach ($departments as $d) {
            if ($d['code'] === 'CS') {
                $cs = $d;
                break;
            }
        }

        $this->assertNotNull($cs, "B.Sc Computer Science must exist in departments");
        $this->assertStringContainsString('Computer Science', $cs['name']);
        $this->assertEquals('Active', $cs['status']);
    }

    public function testAddNewDepartment(): void {
        $testCode = 'TEST_DEPT_' . rand(100, 999);
        $success = create_college_department([
            'code' => $testCode,
            'name' => 'Data Science & AI',
            'faculty_count' => 8,
            'student_count' => 120,
            'hod_name' => 'Dr. Test HOD'
        ]);

        $this->assertTrue($success);

        $departments = get_college_departments();
        $found = false;
        foreach ($departments as $d) {
            if ($d['code'] === $testCode) {
                $found = true;
                $this->assertEquals('Data Science & AI', $d['name']);
                break;
            }
        }
        $this->assertTrue($found, "New department should be retrievable from store");
    }

    public function testCircularCreationAndFiltering(): void {
        $uniqueTitle = 'Semester Exam Circular ' . rand(1000, 9999);
        $circId = create_college_circular([
            'title' => $uniqueTitle,
            'category' => 'Exam',
            'dept_code' => 'CS',
            'content' => 'Semester examination hall tickets are issued.',
            'reference_no' => 'REF-UNIT-01',
            'issued_by' => 'Controller of Examinations'
        ]);

        $this->assertNotEmpty($circId);

        $allCircs = get_college_circulars();
        $found = false;
        foreach ($allCircs as $c) {
            if (($c['id'] ?? '') === $circId) {
                $found = true;
                $this->assertEquals($uniqueTitle, $c['title']);
                $this->assertEquals('CS', $c['dept_code']);
                $this->assertEquals('Exam', $c['category']);
                break;
            }
        }
        $this->assertTrue($found, "Created circular must be found in circulars list");
    }

    public function testEventCreationAndRetrieval(): void {
        $title = 'Inter-Collegiate Hackathon ' . rand(1000, 9999);
        $eventId = create_college_event([
            'title' => $title,
            'event_type' => 'Workshop',
            'dept_code' => 'CS',
            'event_date' => '2026-10-15',
            'venue' => 'CS Computing Lab 2',
            'description' => '24-hour national hackathon for undergrads.'
        ]);

        $this->assertNotEmpty($eventId);

        $events = get_college_events();
        $found = false;
        foreach ($events as $e) {
            if (($e['id'] ?? '') === $eventId) {
                $found = true;
                $this->assertEquals($title, $e['title']);
                $this->assertEquals('Workshop', $e['event_type']);
                break;
            }
        }
        $this->assertTrue($found, "Created event must be found in events list");
    }

    public function testStudentGrievanceLifecycle(): void {
        $testStudentId = 'STU_TEST_' . uniqid() . '_' . rand(1000, 9999);
        $ticketNo = submit_student_grievance([
            'student_id' => $testStudentId,
            'student_name' => 'Akash Unit Test',
            'reg_no' => '21UCS099',
            'department' => 'CS',
            'category' => 'Academic',
            'subject' => 'CIA 2 Mark Clarification',
            'description' => 'Need verification of marks scored in Algorithm Design.'
        ]);

        $this->assertStringStartsWith('GRV-', $ticketNo);

        // Fetch student's grievances
        $grievances = get_student_grievances('All', $testStudentId);
        $this->assertCount(1, $grievances);
        $myGrievance = $grievances[0];
        $this->assertEquals('Pending', $myGrievance['status']);
        $this->assertEquals('CIA 2 Mark Clarification', $myGrievance['subject']);

        // Update status to Resolved with an admin reply
        $grvId = $myGrievance['id'];
        $updated = update_grievance_status($grvId, 'Resolved', 'Marks verified and updated in portal.');
        $this->assertTrue($updated);

        // Verify updated grievance
        $updatedList = get_student_grievances('All', $testStudentId);
        $this->assertEquals('Resolved', $updatedList[0]['status']);
        $this->assertEquals('Marks verified and updated in portal.', $updatedList[0]['admin_reply']);
    }

    public function testDepartmentClassesListing(): void {
        $csClasses = get_department_classes('CS');
        $this->assertCount(5, $csClasses);
        $this->assertArrayHasKey('UG_1', $csClasses);
        $this->assertArrayHasKey('UG_2', $csClasses);
        $this->assertArrayHasKey('UG_3', $csClasses);
        $this->assertArrayHasKey('PG_1', $csClasses);
        $this->assertArrayHasKey('PG_2', $csClasses);

        $this->assertEquals('I B.Sc', $csClasses['UG_1']['short']);
        $this->assertEquals('UG', $csClasses['UG_1']['degree_level']);
        $this->assertEquals('III B.Sc', $csClasses['UG_3']['short']);
        $this->assertEquals('I M.Sc', $csClasses['PG_1']['short']);
        $this->assertEquals('PG', $csClasses['PG_1']['degree_level']);

        $commClasses = get_department_classes('COMM');
        $this->assertEquals('I B.Com', $commClasses['UG_1']['short']);
        $this->assertEquals('I M.Com', $commClasses['PG_1']['short']);

        $histClasses = get_department_classes('HIST');
        $this->assertEquals('I B.A', $histClasses['UG_1']['short']);
        $this->assertEquals('I M.A', $histClasses['PG_1']['short']);

        $botClasses = get_department_classes('BOT');
        $this->assertEquals('I B.Sc', $botClasses['UG_1']['short']);
        $this->assertEquals('I M.Sc', $botClasses['PG_1']['short']);

        $itClasses = get_department_classes('IT');
        $this->assertEquals('I B.Sc', $itClasses['UG_1']['short']);
        $this->assertEquals('I M.Sc', $itClasses['PG_1']['short']);
    }

    public function testExact14CollegeDepartmentsConfigured(): void {
        $departments = get_college_departments();
        $codes = array_map(fn($d) => $d['code'], $departments);

        $expected14 = [
            'TAM', 'ENG', 'HIST', 'ECO', 'COMM',
            'MATH', 'PHY', 'CHEM', 'BOT', 'ZOO', 'STAT', 'CS', 'BCA', 'IT'
        ];

        foreach ($expected14 as $exp) {
            $this->assertContains($exp, $codes, "Department [$exp] must exist in the 14 college departments");
        }

        $this->assertNotContains('BBA', $codes, "BBA must not be in the college departments list");
    }

    public function testStudentYearInfoClassification(): void {
        $ug3Student = [
            'course' => 'CS',
            'main_subject' => 'III B.Sc Computer Science',
            'roll_no' => '24CSC102'
        ];
        $info3 = get_student_year_info($ug3Student);
        $this->assertEquals('UG_3', $info3['class_code']);
        $this->assertEquals(3, $info3['year_num']);
        $this->assertEquals('UG', $info3['degree_level']);
        $this->assertEquals('UG_3', $info3['filter_tag']);
        $this->assertEquals('III B.Sc', $info3['short']);

        $pg1Student = [
            'course' => 'CS',
            'main_subject' => 'I M.Sc Computer Science',
            'roll_no' => '26PCS001'
        ];
        $infoPg = get_student_year_info($pg1Student);
        $this->assertEquals('PG_1', $infoPg['class_code']);
        $this->assertEquals(1, $infoPg['year_num']);
        $this->assertEquals('PG', $infoPg['degree_level']);
        $this->assertEquals('PG_1', $infoPg['filter_tag']);
        $this->assertEquals('I M.Sc', $infoPg['short']);
    }

    public function testIsolatedClassTimetables(): void {
        $ug1Tt = get_class_timetable('CS', 'UG_1');
        $ug3Tt = get_class_timetable('CS', 'UG_3');

        $this->assertNotEmpty($ug1Tt);
        $this->assertNotEmpty($ug3Tt);

        // Save slot in UG_1 Day I Hour 1
        $saved = save_class_timetable_slot('CS', 'UG_1', 'I', 1, 'Unit Test Isolated Slot', 1);
        $this->assertTrue($saved);

        // Fetch refreshed timetables
        $refreshedUg1 = get_class_timetable('CS', 'UG_1');
        $refreshedUg3 = get_class_timetable('CS', 'UG_3');

        $ug1Slot1 = null;
        foreach ($refreshedUg1['I'] as $slot) {
            if ($slot['hour'] === 1) {
                $ug1Slot1 = $slot;
                break;
            }
        }
        $this->assertNotNull($ug1Slot1);
        $this->assertEquals('Unit Test Isolated Slot', $ug1Slot1['sub']);

        $ug3Slot1 = null;
        foreach ($refreshedUg3['I'] as $slot) {
            if ($slot['hour'] === 1) {
                $ug3Slot1 = $slot;
                break;
            }
        }
        $this->assertNotNull($ug3Slot1);
        $this->assertNotEquals('Unit Test Isolated Slot', $ug3Slot1['sub'], "UG_3 schedule must remain isolated from UG_1 edits");
    }

    public function testBioDataAndUmisYearResolution(): void {
        // Test Bio Data Row
        $bioRow = [
            'department' => 'TAM',
            'class' => 'I B.A',
            'academic_year' => '2026-2029'
        ];
        $infoBio = get_student_year_info($bioRow);
        $this->assertEquals('UG_1', $infoBio['class_code']);
        $this->assertEquals(1, $infoBio['year_num']);
        $this->assertEquals('UG', $infoBio['degree_level']);
        $this->assertEquals('I B.A', $infoBio['short']);

        // Test Bio Data PG Row
        $bioPgRow = [
            'department' => 'COMM',
            'class' => 'II M.Com',
            'academic_year' => '2025-2027'
        ];
        $infoPgBio = get_student_year_info($bioPgRow);
        $this->assertEquals('PG_2', $infoPgBio['class_code']);
        $this->assertEquals(2, $infoPgBio['year_num']);
        $this->assertEquals('PG', $infoPgBio['degree_level']);
        $this->assertEquals('II M.Com', $infoPgBio['short']);

        // Test UMIS Row
        $umisRow = [
            'course' => 'CS',
            'year_of_study' => '2nd Year',
            'roll_no' => '25CSC001'
        ];
        $infoUmis = get_student_year_info($umisRow);
        $this->assertEquals('UG_2', $infoUmis['class_code']);
        $this->assertEquals(2, $infoUmis['year_num']);
        $this->assertEquals('UG', $infoUmis['degree_level']);
        $this->assertEquals('II B.Sc', $infoUmis['short']);
    }
}


