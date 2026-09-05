<?php
namespace SRMS\Tests\Course;

use PHPUnit\Framework\TestCase;
use SRMS\Course\SubjectController;
use SRMS\Database\DatabaseConnection;
use SRMS\Database\RecordNotFoundException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;
use SRMS\Tests\Mocks\PDOMockBuilder;

class CourseSubjectManagementTest extends TestCase {
    protected DatabaseConnection $db;
    protected SubjectController $subjectController;

    protected function setUp(): void {
        parent::setUp();
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $this->db = new DatabaseConnection($pdo);
        $this->subjectController = new SubjectController($this->db);
    }

    public function testAddSubjectSuccess(): void {
        $id = $this->subjectController->addSubject(
            'faculty@college.edu',
            'Data Structures',
            'CS201',
            'II CSE - A'
        );

        $this->assertGreaterThan(0, $id);

        $subjects = $this->subjectController->getSubjectsByFaculty('faculty@college.edu');
        $this->assertCount(1, $subjects);
        $this->assertEquals('Data Structures', $subjects[0]['subject_name']);
        $this->assertEquals('CS201', $subjects[0]['subject_code']);
        $this->assertEquals('II CSE - A', $subjects[0]['class_assigned']);
    }

    public function testAddSubjectFailsWhenRequiredFieldsMissing(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Subject Name & Code are required!");

        $this->subjectController->addSubject('faculty@college.edu', '', 'CS201');
    }

    public function testDuplicateSubjectCodePrevented(): void {
        $this->subjectController->addSubject(
            'faculty@college.edu',
            'Operating Systems',
            'CS301',
            'III CSE'
        );

        // Attempt to add another subject with same code for this faculty
        $this->expectException(UniqueConstraintException::class);
        $this->expectExceptionMessage("Subject with code 'CS301' already assigned to this faculty!");

        $this->subjectController->addSubject(
            'faculty@college.edu',
            'Modern Operating Systems',
            'cs301', // case-insensitive duplicate
            'III CSE'
        );
    }

    public function testDifferentFacultyCanHaveSameSubjectCode(): void {
        $id1 = $this->subjectController->addSubject('faculty1@college.edu', 'C Programming', 'CS101', 'I CSE');
        $id2 = $this->subjectController->addSubject('faculty2@college.edu', 'C Programming', 'CS101', 'I IT');

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotEquals($id1, $id2);
    }

    public function testUpdateSubjectSuccess(): void {
        $id = $this->subjectController->addSubject('faculty@college.edu', 'Networks', 'CS401', 'IV CSE');

        $affected = $this->subjectController->updateSubject($id, 'Computer Networks & Security', 'CS401', 'IV CSE - Sec B');
        $this->assertEquals(1, $affected);

        $subjects = $this->subjectController->getSubjectsByFaculty('faculty@college.edu');
        $this->assertEquals('Computer Networks & Security', $subjects[0]['subject_name']);
        $this->assertEquals('IV CSE - Sec B', $subjects[0]['class_assigned']);
    }

    public function testUpdateSubjectFailsWithDuplicateCode(): void {
        $id1 = $this->subjectController->addSubject('faculty@college.edu', 'Subject One', 'SUB1');
        $id2 = $this->subjectController->addSubject('faculty@college.edu', 'Subject Two', 'SUB2');

        $this->expectException(UniqueConstraintException::class);
        $this->expectExceptionMessage("Subject code 'SUB1' is already used by another record.");

        // Try to update subject 2 with subject 1's code
        $this->subjectController->updateSubject($id2, 'Subject Two Modified', 'SUB1', 'All');
    }

    public function testUpdateNonExistentSubjectThrowsException(): void {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Subject with ID 9999 not found.");

        $this->subjectController->updateSubject(9999, 'Dummy', 'DUMMY', 'None');
    }

    public function testDeleteSubject(): void {
        $id = $this->subjectController->addSubject('faculty@college.edu', 'Discrete Mathematics', 'MA201');

        $deleted = $this->subjectController->deleteSubject($id);
        $this->assertEquals(1, $deleted);

        $subjects = $this->subjectController->getSubjectsByFaculty('faculty@college.edu');
        $this->assertCount(0, $subjects);
    }

    public function testDeleteNonExistentSubjectThrowsException(): void {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Subject with ID 9999 not found.");

        $this->subjectController->deleteSubject(9999);
    }
}
