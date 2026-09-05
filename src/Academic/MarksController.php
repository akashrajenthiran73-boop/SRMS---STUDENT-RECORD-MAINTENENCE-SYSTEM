<?php
namespace SRMS\Academic;

use SRMS\Database\DatabaseConnection;
use SRMS\Database\ValidationException;

class MarksController {
    protected ?DatabaseConnection $db;

    public function __construct(?DatabaseConnection $db = null) {
        $this->db = $db;
    }

    /**
     * Parses raw student marks data into a clean structured array.
     * Handles JSON strings, nested JSON, legacy column keys (I.A, U.E).
     */
    public function parseStudentMarks($rawData): array {
        if (empty($rawData)) {
            return [];
        }

        $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;
        if (is_string($data)) {
            $data = json_decode($data, true);
        }
        if (!is_array($data)) {
            return [];
        }

        $cleanData = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ia = isset($row['ia']) ? (int)$row['ia'] : (isset($row['ia_marks']) ? (int)$row['ia_marks'] : (isset($row['I.A']) ? (int)$row['I.A'] : 0));
            $ue = isset($row['ue']) ? (int)$row['ue'] : (isset($row['ue_marks']) ? (int)$row['ue_marks'] : (isset($row['U.E']) ? (int)$row['U.E'] : 0));
            $subject = $row['subject'] ?? $row['Subject'] ?? $row['subject_code'] ?? 'Subject';

            $total = isset($row['total']) ? (int)$row['total'] : ($ia + $ue);
            if ($total === 0 && ($ia > 0 || $ue > 0)) {
                $total = $ia + $ue;
            }

            $pfStatus = $row['pass_fail'] ?? $row['result'] ?? $row['pf'] ?? '';
            if (empty($pfStatus)) {
                $pfStatus = $this->determinePassFail($ia, $ue, $total);
            }

            $cleanData[] = [
                'subject'   => trim((string)$subject),
                'ia'        => $ia,
                'ue'        => $ue,
                'total'     => $total,
                'result'    => strtoupper(trim($pfStatus)),
                'pass_fail' => strtoupper(trim($pfStatus)),
                'grade'     => $this->calculateGrade($total)
            ];
        }

        return $cleanData;
    }

    /**
     * Determines pass/fail status based on standard academic criteria:
     * IA >= 10 AND UE >= 30 AND Total >= 40.
     */
    public function determinePassFail(int $ia, int $ue, int $total): string {
        return ($ia >= 10 && $ue >= 30 && $total >= 40) ? 'PASS' : 'FAIL';
    }

    /**
     * Calculates letter grade according to 10-point scale:
     * 90-100: O, 80-89: A+, 70-79: A, 60-69: B+, 55-59: B, 50-54: C, 40-49: P, <40: F
     */
    public function calculateGrade(int $marks): string {
        if ($marks >= 90 && $marks <= 100) return 'O';
        if ($marks >= 80) return 'A+';
        if ($marks >= 70) return 'A';
        if ($marks >= 60) return 'B+';
        if ($marks >= 55) return 'B';
        if ($marks >= 50) return 'C';
        if ($marks >= 40) return 'P';
        return 'F';
    }

    /**
     * Maps letter grade to grade point (0 to 10).
     */
    public function calculateGradePoint(string $grade): int {
        return match (strtoupper(trim($grade))) {
            'O'     => 10,
            'A+'    => 9,
            'A'     => 8,
            'B+'    => 7,
            'B'     => 6,
            'C'     => 5,
            'P'     => 4,
            default => 0,
        };
    }

    /**
     * Calculates Semester GPA from an array of subjects with credits and marks/grades.
     * Formula: GPA = SUM(Credits * GradePoint) / SUM(Credits)
     */
    public function calculateGPA(array $subjects): float {
        $totalCredits = 0;
        $totalWeightedPoints = 0;

        foreach ($subjects as $sub) {
            $credits = isset($sub['credits']) ? (float)$sub['credits'] : 3.0;
            $marks = (int)($sub['total'] ?? $sub['marks'] ?? (($sub['ia'] ?? 0) + ($sub['ue'] ?? 0)));
            $grade = $sub['grade'] ?? $this->calculateGrade($marks);
            $gradePoint = $this->calculateGradePoint($grade);

            $totalCredits += $credits;
            $totalWeightedPoints += ($credits * $gradePoint);
        }

        if ($totalCredits <= 0) {
            return 0.0;
        }

        return round($totalWeightedPoints / $totalCredits, 2);
    }

    /**
     * Computes statistics and result analysis metrics.
     */
    public function generateResultAnalysis(array $cleanMarks): array {
        $totalRecords = count($cleanMarks);
        if ($totalRecords === 0) {
            return [
                'total_records' => 0,
                'passed_count'  => 0,
                'failed_count'  => 0,
                'pass_rate'     => 0.0,
                'averages'      => []
            ];
        }

        $passedCount = count(array_filter($cleanMarks, fn($m) => ($m['marks_scored'] ?? $m['total'] ?? 0) >= 40));
        $failedCount = $totalRecords - $passedCount;
        $passRate = round(($passedCount / $totalRecords) * 100, 1);

        // Group by semester
        $semGroups = [];
        foreach ($cleanMarks as $m) {
            $sem = $m['semester'] ?? 1;
            $score = (int)($m['marks_scored'] ?? $m['total'] ?? 0);
            $semGroups[$sem][] = $score;
        }

        ksort($semGroups);
        $averages = [];
        foreach ($semGroups as $sem => $scores) {
            $averages[$sem] = round(array_sum($scores) / count($scores), 1);
        }

        return [
            'total_records' => $totalRecords,
            'passed_count'  => $passedCount,
            'failed_count'  => $failedCount,
            'pass_rate'     => $passRate,
            'averages'      => $averages
        ];
    }
}
