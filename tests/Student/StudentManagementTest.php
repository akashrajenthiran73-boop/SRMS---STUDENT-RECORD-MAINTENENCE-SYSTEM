<?php
namespace SRMS\Tests\Student;

use PHPUnit\Framework\TestCase;
use SRMS\Student\StudentController;
use SRMS\Database\DatabaseConnection;
use SRMS\Database\RecordNotFoundException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;
use SRMS\Tests\Mocks\PDOMockBuilder;

class StudentManagementTest extends TestCase {
    protected DatabaseConnection $db;
    protected StudentController $studentController;

    protected function setUp(): void {
        parent::setUp();
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $this->db = new DatabaseConnection($pdo);
        $this->studentController = new StudentController($this->db);
    }

    public function testRollNumberValidation(): void {
        $this->assertTrue($this->studentController->isValidRollNumber('23CS101'));
        $this->assertTrue($this->studentController->isValidRollNumber('REG_2024_01'));
        $this->assertTrue($this->studentController->isValidRollNumber('STU-9999'));

        $this->assertFalse($this->studentController->isValidRollNumber(''));
        $this->assertFalse($this->studentController->isValidRollNumber('ab')); // too short (<4)
        $this->assertFalse($this->studentController->isValidRollNumber('INVALID ROLL WITH SPACES'));
        $this->assertFalse($this->studentController->isValidRollNumber('BAD<SCRIPT>123'));
    }

    public function testMultiStageFormStage1MarksEncoding(): void {
        $stage1Post = [
            'name' => 'John Doe',
            'reg_no' => '23CS105',
            'email' => 'john@college.edu',
            'sem1_subject' => ['Mathematics I', 'Physics I', 'English'],
            'sem1_ue' => [60, 55, 70],
            'sem1_ia' => [22, 20, 24],
            'sem1_total' => [82, 75, 94],
            'sem1_pf' => ['PASS', 'PASS', 'PASS'],
            'next' => 'Next Page'
        ];

        $processed1 = $this->studentController->processStage1($stage1Post);

        $this->assertArrayNotHasKey('sem1_subject', $processed1);
        $this->assertArrayNotHasKey('sem1_ue', $processed1);
        $this->assertArrayHasKey('sem1_marks', $processed1);

        $marks = json_decode($processed1['sem1_marks'], true);
        $this->assertCount(3, $marks);
        $this->assertEquals('Mathematics I', $marks[0]['subject']);
        $this->assertEquals(60, $marks[0]['ue']);
        $this->assertEquals(22, $marks[0]['ia']);
        $this->assertEquals(82, $marks[0]['total']);
        $this->assertEquals('PASS', $marks[0]['pass_fail']);
    }

    public function testMultiStageFormStage2MergeAndFieldNormalization(): void {
        $stage1Data = [
            'name' => 'John Doe',
            'reg_no' => '23CS105',
            'email' => 'john@college.edu',
            'dob' => '',                    // empty date -> should be NULL
            'date_of_joining' => '',        // empty date -> should be NULL
            'date_of_leaving' => '2026-05-30',
            'days_present' => '',           // empty numeric -> should be NULL
            'working_days' => '180',
            'class_rank' => '',             // empty numeric -> should be NULL
            'photo_url' => '',              // empty photo -> should get fallback
            'sem1_marks' => '[]',
            'sem2_marks' => '[]',
            'sem3_marks' => '[]'
        ];

        $stage2Post = [
            'address' => '123 College Road',
            'sem4_subject' => ['Operating Systems'],
            'sem4_ue' => [65],
            'sem4_ia' => [20],
            'sem4_total' => [85],
            'sem4_pf' => ['PASS'],
            'submit' => 'Finish'
        ];

        $finalData = $this->studentController->processStage2($stage1Data, $stage2Post);

        // Fallback photo
        $this->assertEquals('https://via.placeholder.com/150?text=No+Photo', $finalData['photo_url']);

        // Null conversion
        $this->assertNull($finalData['dob']);
        $this->assertNull($finalData['date_of_joining']);
        $this->assertEquals('2026-05-30', $finalData['date_of_leaving']);
        $this->assertNull($finalData['days_present']);
        $this->assertEquals('180', $finalData['working_days']);
        $this->assertNull($finalData['class_rank']);

        // Action tokens removed
        $this->assertArrayNotHasKey('submit', $finalData);
        $this->assertArrayNotHasKey('sem4_subject', $finalData);

        // Sem 4 marks encoded
        $sem4Marks = json_decode($finalData['sem4_marks'], true);
        $this->assertCount(1, $sem4Marks);
        $this->assertEquals('Operating Systems', $sem4Marks[0]['subject']);
    }

    public function testStage2FailsWithoutStage1Session(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Stage 1 student data is missing. Please restart the registration wizard.");

        $this->studentController->processStage2([], ['submit' => 'Finish']);
    }

    public function testBiodataFormSanitization(): void {
        $postData = [
            'name' => 'Alice Bob',
            'prev_attendance' => '85.5%',       // contains % -> strip it
            'parent_income' => '',              // empty numeric -> null
            'community' => 'Select',            // 'Select' placeholder -> null
            'accommodation' => '',              // empty select -> null
            'travel_concession' => 'Bus',
            'ex_serviceman' => 'Select',
            'submit' => 'Save'
        ];

        $sanitized = $this->studentController->sanitizeBiodataForm($postData);

        $this->assertEquals('85.5', $sanitized['prev_attendance']);
        $this->assertNull($sanitized['parent_income']);
        $this->assertNull($sanitized['community']);
        $this->assertNull($sanitized['accommodation']);
        $this->assertEquals('Bus', $sanitized['travel_concession']);
        $this->assertNull($sanitized['ex_serviceman']);
        $this->assertArrayNotHasKey('submit', $sanitized);
    }

    public function testStudentCreationAndRollNumberUniqueness(): void {
        $id1 = $this->studentController->createStudent([
            'name' => 'First Student',
            'reg_no' => '23CS101',
            'email' => 'first@college.edu'
        ]);
        $this->assertGreaterThan(0, $id1);

        // Attempt duplicate roll number
        $this->expectException(UniqueConstraintException::class);
        $this->expectExceptionMessage("Student with Register Number '23CS101' already exists.");

        $this->studentController->createStudent([
            'name' => 'Duplicate Roll Student',
            'reg_no' => '23CS101',
            'email' => 'duplicate@college.edu'
        ]);
    }

    public function testStudentUpdate(): void {
        $id = $this->studentController->createStudent([
            'name' => 'Original Name',
            'reg_no' => '23CS102',
            'email' => 'orig@college.edu'
        ]);

        $affected = $this->studentController->updateStudent($id, [
            'name' => 'Updated Name',
            'days_present' => '80',
            'working_days' => '90'
        ]);

        $this->assertEquals(1, $affected);

        $updated = $this->db->fetchOne("SELECT * FROM students WHERE id = :id", [':id' => $id]);
        $this->assertEquals('Updated Name', $updated['name']);
        $this->assertEquals(80, $updated['days_present']);
    }

    public function testStudentUpdateNonExistentThrowsException(): void {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Student record with ID 9999 not found.");

        $this->studentController->updateStudent(9999, ['name' => 'Nobody']);
    }

    public function testStudentDeletion(): void {
        $id = $this->studentController->createStudent([
            'name' => 'Delete Me',
            'reg_no' => '23CS109',
            'email' => 'del@college.edu'
        ]);

        $deleted = $this->studentController->deleteStudent($id);
        $this->assertEquals(1, $deleted);

        $check = $this->db->fetchOne("SELECT * FROM students WHERE id = :id", [':id' => $id]);
        $this->assertNull($check);
    }
}
