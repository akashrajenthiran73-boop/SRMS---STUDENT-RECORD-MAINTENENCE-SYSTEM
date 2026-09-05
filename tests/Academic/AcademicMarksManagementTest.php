<?php
namespace SRMS\Tests\Academic;

use PHPUnit\Framework\TestCase;
use SRMS\Academic\MarksController;

class AcademicMarksManagementTest extends TestCase {
    protected MarksController $marksController;

    protected function setUp(): void {
        parent::setUp();
        $this->marksController = new MarksController();
    }

    public function testParseMarksFromJsonString(): void {
        $rawJson = json_encode([
            ['subject' => 'Data Structures', 'ia' => '22', 'ue' => '65', 'total' => '87'],
            ['subject' => 'Algorithms', 'ia' => '18', 'ue' => '52', 'total' => '70']
        ]);

        $parsed = $this->marksController->parseStudentMarks($rawJson);

        $this->assertCount(2, $parsed);
        $this->assertEquals('Data Structures', $parsed[0]['subject']);
        $this->assertEquals(22, $parsed[0]['ia']);
        $this->assertEquals(65, $parsed[0]['ue']);
        $this->assertEquals(87, $parsed[0]['total']);
        $this->assertEquals('PASS', $parsed[0]['result']);
        $this->assertEquals('A+', $parsed[0]['grade']);
    }

    public function testParseMarksWithLegacyKeysAndAutoTotal(): void {
        $legacyData = [
            ['Subject' => 'Computer Networks', 'I.A' => '15', 'U.E' => '45', 'total' => 0]
        ];

        $parsed = $this->marksController->parseStudentMarks($legacyData);

        $this->assertCount(1, $parsed);
        $this->assertEquals('Computer Networks', $parsed[0]['subject']);
        $this->assertEquals(15, $parsed[0]['ia']);
        $this->assertEquals(45, $parsed[0]['ue']);
        // Auto-total: 15 + 45 = 60
        $this->assertEquals(60, $parsed[0]['total']);
        $this->assertEquals('PASS', $parsed[0]['result']);
        $this->assertEquals('B+', $parsed[0]['grade']);
    }

    public function testPassFailStatusBoundaries(): void {
        // 1. Exact pass boundary (IA=10, UE=30, Total=40)
        $this->assertEquals('PASS', $this->marksController->determinePassFail(10, 30, 40));

        // 2. IA below 10 -> FAIL even if total >= 40
        $this->assertEquals('FAIL', $this->marksController->determinePassFail(9, 50, 59));

        // 3. UE below 30 -> FAIL even if total >= 40
        $this->assertEquals('FAIL', $this->marksController->determinePassFail(25, 29, 54));

        // 4. Total below 40 -> FAIL
        $this->assertEquals('FAIL', $this->marksController->determinePassFail(10, 29, 39));

        // 5. Zero marks -> FAIL
        $this->assertEquals('FAIL', $this->marksController->determinePassFail(0, 0, 0));

        // 6. High marks -> PASS
        $this->assertEquals('PASS', $this->marksController->determinePassFail(25, 75, 100));
    }

    public function testLetterGradingScale(): void {
        $this->assertEquals('O', $this->marksController->calculateGrade(100));
        $this->assertEquals('O', $this->marksController->calculateGrade(90));
        $this->assertEquals('A+', $this->marksController->calculateGrade(89));
        $this->assertEquals('A+', $this->marksController->calculateGrade(80));
        $this->assertEquals('A', $this->marksController->calculateGrade(79));
        $this->assertEquals('A', $this->marksController->calculateGrade(70));
        $this->assertEquals('B+', $this->marksController->calculateGrade(69));
        $this->assertEquals('B+', $this->marksController->calculateGrade(60));
        $this->assertEquals('B', $this->marksController->calculateGrade(59));
        $this->assertEquals('B', $this->marksController->calculateGrade(55));
        $this->assertEquals('C', $this->marksController->calculateGrade(54));
        $this->assertEquals('C', $this->marksController->calculateGrade(50));
        $this->assertEquals('P', $this->marksController->calculateGrade(49));
        $this->assertEquals('P', $this->marksController->calculateGrade(40));
        $this->assertEquals('F', $this->marksController->calculateGrade(39));
        $this->assertEquals('F', $this->marksController->calculateGrade(0));
    }

    public function testGradePointMapping(): void {
        $this->assertEquals(10, $this->marksController->calculateGradePoint('O'));
        $this->assertEquals(9, $this->marksController->calculateGradePoint('A+'));
        $this->assertEquals(8, $this->marksController->calculateGradePoint('A'));
        $this->assertEquals(7, $this->marksController->calculateGradePoint('B+'));
        $this->assertEquals(6, $this->marksController->calculateGradePoint('B'));
        $this->assertEquals(5, $this->marksController->calculateGradePoint('C'));
        $this->assertEquals(4, $this->marksController->calculateGradePoint('P'));
        $this->assertEquals(0, $this->marksController->calculateGradePoint('F'));
        $this->assertEquals(0, $this->marksController->calculateGradePoint('RA'));
    }

    public function testWeightedGPACalculation(): void {
        $subjects = [
            ['subject' => 'Compiler Design', 'credits' => 4, 'total' => 92], // O -> GP 10 -> 40
            ['subject' => 'Cloud Computing', 'credits' => 4, 'total' => 82], // A+ -> GP 9 -> 36
            ['subject' => 'Software Eng',    'credits' => 3, 'total' => 74], // A -> GP 8 -> 24
            ['subject' => 'Web Tech Lab',    'credits' => 2, 'total' => 95], // O -> GP 10 -> 20
        ];

        // Total Credits = 4 + 4 + 3 + 2 = 13
        // Total Points = 40 + 36 + 24 + 20 = 120
        // Expected GPA = 120 / 13 = 9.23
        $gpa = $this->marksController->calculateGPA($subjects);
        $this->assertEquals(9.23, $gpa);
    }

    public function testResultAnalysisSummaryAndSemesterAverages(): void {
        $cleanMarks = [
            ['semester' => 1, 'marks_scored' => 85],
            ['semester' => 1, 'marks_scored' => 75],
            ['semester' => 1, 'marks_scored' => 35], // fail
            ['semester' => 2, 'marks_scored' => 90],
            ['semester' => 2, 'marks_scored' => 60],
        ];

        $analysis = $this->marksController->generateResultAnalysis($cleanMarks);

        $this->assertEquals(5, $analysis['total_records']);
        $this->assertEquals(4, $analysis['passed_count']);
        $this->assertEquals(1, $analysis['failed_count']);
        // 4 / 5 * 100 = 80.0%
        $this->assertEquals(80.0, $analysis['pass_rate']);

        // Sem 1 average: (85 + 75 + 35) / 3 = 65.0
        $this->assertEquals(65.0, $analysis['averages'][1]);
        // Sem 2 average: (90 + 60) / 2 = 75.0
        $this->assertEquals(75.0, $analysis['averages'][2]);
    }

    public function testEmptyMarksReturnEmptyStructure(): void {
        $this->assertEmpty($this->marksController->parseStudentMarks([]));
        $this->assertEmpty($this->marksController->parseStudentMarks(''));

        $emptyAnalysis = $this->marksController->generateResultAnalysis([]);
        $this->assertEquals(0, $emptyAnalysis['total_records']);
        $this->assertEquals(0.0, $emptyAnalysis['pass_rate']);
    }
}
