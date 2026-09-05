<?php
namespace SRMS\Student;

use SRMS\Database\DatabaseConnection;
use SRMS\Database\RecordNotFoundException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;

class StudentController {
    protected DatabaseConnection $db;

    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    /**
     * Validates roll number format (alphanumeric, 4 to 20 characters).
     */
    public function isValidRollNumber(string $rollNo): bool {
        return (bool)preg_match('/^[a-zA-Z0-9_-]{4,20}$/', trim($rollNo));
    }

    /**
     * Stage 1 of Student registration: encodes Sem 1-3 dynamic marks into JSON format.
     */
    public function processStage1(array $postData): array {
        $clean = $postData;

        // Validation of basic identity
        $name = trim($clean['name'] ?? '');
        $regNo = trim($clean['reg_no'] ?? $clean['register_no'] ?? '');

        if (empty($name)) {
            throw new ValidationException("Student name is required.");
        }

        if (!empty($regNo) && !$this->isValidRollNumber($regNo)) {
            throw new ValidationException("Invalid register number format: $regNo");
        }

        // Process Sem 1-3 dynamic subjects
        for ($sem = 1; $sem <= 3; $sem++) {
            $marksData = [];
            if (isset($clean["sem{$sem}_subject"]) && is_array($clean["sem{$sem}_subject"])) {
                $subjects = $clean["sem{$sem}_subject"];
                $ues = $clean["sem{$sem}_ue"] ?? [];
                $ias = $clean["sem{$sem}_ia"] ?? [];
                $totals = $clean["sem{$sem}_total"] ?? [];
                $pfs = $clean["sem{$sem}_pf"] ?? [];

                for ($i = 0; $i < count($subjects); $i++) {
                    if (!empty(trim($subjects[$i] ?? ''))) {
                        $marksData[] = [
                            'subject'   => trim($subjects[$i]),
                            'ue'        => ($ues[$i] !== '' && $ues[$i] !== null) ? (int)$ues[$i] : null,
                            'ia'        => ($ias[$i] !== '' && $ias[$i] !== null) ? (int)$ias[$i] : null,
                            'total'     => ($totals[$i] !== '' && $totals[$i] !== null) ? (int)$totals[$i] : null,
                            'pass_fail' => $pfs[$i] ?? ''
                        ];
                    }
                }
            }

            $clean["sem{$sem}_marks"] = json_encode($marksData);

            unset($clean["sem{$sem}_subject"]);
            unset($clean["sem{$sem}_ue"]);
            unset($clean["sem{$sem}_ia"]);
            unset($clean["sem{$sem}_total"]);
            unset($clean["sem{$sem}_pf"]);
        }

        return $clean;
    }

    /**
     * Stage 2 of Student registration: encodes Sem 4-6 dynamic marks, merges with Stage 1, and normalizes fields.
     */
    public function processStage2(array $stage1Data, array $stage2PostData): array {
        if (empty($stage1Data)) {
            throw new ValidationException("Stage 1 student data is missing. Please restart the registration wizard.");
        }

        $stage2Clean = $stage2PostData;

        // Process Sem 4-6 dynamic subjects
        for ($sem = 4; $sem <= 6; $sem++) {
            $marksData = [];
            if (isset($stage2Clean["sem{$sem}_subject"]) && is_array($stage2Clean["sem{$sem}_subject"])) {
                $subjects = $stage2Clean["sem{$sem}_subject"];
                $ues = $stage2Clean["sem{$sem}_ue"] ?? [];
                $ias = $stage2Clean["sem{$sem}_ia"] ?? [];
                $totals = $stage2Clean["sem{$sem}_total"] ?? [];
                $pfs = $stage2Clean["sem{$sem}_pf"] ?? [];

                for ($i = 0; $i < count($subjects); $i++) {
                    if (!empty(trim($subjects[$i] ?? ''))) {
                        $marksData[] = [
                            'subject'   => trim($subjects[$i]),
                            'ue'        => ($ues[$i] !== '' && $ues[$i] !== null) ? (int)$ues[$i] : null,
                            'ia'        => ($ias[$i] !== '' && $ias[$i] !== null) ? (int)$ias[$i] : null,
                            'total'     => ($totals[$i] !== '' && $totals[$i] !== null) ? (int)$totals[$i] : null,
                            'pass_fail' => $pfs[$i] ?? ''
                        ];
                    }
                }
            }

            $stage1Data["sem{$sem}_marks"] = json_encode($marksData);

            unset($stage2Clean["sem{$sem}_subject"]);
            unset($stage2Clean["sem{$sem}_ue"]);
            unset($stage2Clean["sem{$sem}_ia"]);
            unset($stage2Clean["sem{$sem}_total"]);
            unset($stage2Clean["sem{$sem}_pf"]);
        }

        $merged = array_merge($stage1Data, $stage2Clean);

        // Remove button tokens
        unset($merged['next'], $merged['submit'], $merged['back']);

        // Default Photo URL
        if (empty($merged['photo_url'])) {
            $merged['photo_url'] = 'https://via.placeholder.com/150?text=No+Photo';
        }

        // Convert empty dates to NULL
        foreach (['dob', 'date_of_joining', 'date_of_leaving'] as $dateField) {
            if (isset($merged[$dateField]) && trim((string)$merged[$dateField]) === '') {
                $merged[$dateField] = null;
            }
        }

        // Convert empty numerics to NULL
        foreach (['days_present', 'working_days', 'class_rank'] as $numField) {
            if (isset($merged[$numField]) && trim((string)$merged[$numField]) === '') {
                $merged[$numField] = null;
            }
        }

        return $merged;
    }

    /**
     * Sanitizes Biodata Form fields, stripping % from attendance and converting empty selects to null.
     */
    public function sanitizeBiodataForm(array $postData): array {
        $clean = $postData;
        unset($clean['submit']);

        if (empty($clean['id'])) {
            unset($clean['id']);
        }

        // Attendance % clean
        if (isset($clean['prev_attendance'])) {
            $clean['prev_attendance'] = str_replace('%', '', (string)$clean['prev_attendance']);
        }

        // Numeric fields to null if empty
        $numericFields = ['parent_income'];
        foreach ($numericFields as $field) {
            if (isset($clean[$field]) && trim((string)$clean[$field]) === '') {
                $clean[$field] = null;
            }
        }

        // Select fields to null if empty or 'Select'
        $selectFields = ['community', 'accommodation', 'travel_concession', 'ex_serviceman'];
        foreach ($selectFields as $field) {
            if (isset($clean[$field]) && (trim((string)$clean[$field]) === '' || $clean[$field] === 'Select')) {
                $clean[$field] = null;
            }
        }

        return $clean;
    }

    /**
     * Inserts student record with roll number uniqueness check.
     */
    public function createStudent(array $data): int {
        $regNo = trim($data['reg_no'] ?? $data['register_no'] ?? '');

        if (!empty($regNo)) {
            $existing = $this->db->fetchOne("SELECT id FROM students WHERE reg_no = :reg_no LIMIT 1", [':reg_no' => $regNo]);
            if ($existing) {
                throw new UniqueConstraintException("Student with Register Number '$regNo' already exists.", 1062, null, 'reg_no', $regNo);
            }
        }

        return $this->db->insert('students', $data);
    }

    /**
     * Updates an existing student record with semester marks processing.
     */
    public function updateStudent(int $id, array $data): int {
        $existing = $this->db->fetchOne("SELECT id FROM students WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$existing) {
            throw new RecordNotFoundException("Student record with ID $id not found.");
        }

        $clean = $data;
        unset($clean['update'], $clean['id']);

        // Process Sem 1-6 marks if present in form arrays
        for ($sem = 1; $sem <= 6; $sem++) {
            if (isset($clean["sem{$sem}_subject"]) && is_array($clean["sem{$sem}_subject"])) {
                $subjects = $clean["sem{$sem}_subject"];
                $ues = $clean["sem{$sem}_ue"] ?? [];
                $ias = $clean["sem{$sem}_ia"] ?? [];
                $totals = $clean["sem{$sem}_total"] ?? [];
                $pfs = $clean["sem{$sem}_pf"] ?? [];

                $marksData = [];
                for ($i = 0; $i < count($subjects); $i++) {
                    if (!empty(trim($subjects[$i] ?? ''))) {
                        $marksData[] = [
                            'subject'   => trim($subjects[$i]),
                            'ue'        => (int)($ues[$i] ?? 0),
                            'ia'        => (int)($ias[$i] ?? 0),
                            'total'     => (int)($totals[$i] ?? 0),
                            'pass_fail' => $pfs[$i] ?? ''
                        ];
                    }
                }
                $clean["sem{$sem}_marks"] = json_encode($marksData);

                unset($clean["sem{$sem}_subject"], $clean["sem{$sem}_ue"], $clean["sem{$sem}_ia"], $clean["sem{$sem}_total"], $clean["sem{$sem}_pf"]);
            }
        }

        // Numeric fields to null if empty
        foreach (['days_present', 'working_days', 'class_rank'] as $field) {
            if (isset($clean[$field]) && trim((string)$clean[$field]) === '') {
                $clean[$field] = null;
            }
        }

        return $this->db->update('students', $clean, ['id' => $id]);
    }

    /**
     * Deletes student by ID.
     */
    public function deleteStudent(int $id): int {
        return $this->db->delete('students', ['id' => $id]);
    }
}
