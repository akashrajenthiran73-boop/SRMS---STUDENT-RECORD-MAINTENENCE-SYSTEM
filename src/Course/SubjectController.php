<?php
namespace SRMS\Course;

use SRMS\Database\DatabaseConnection;
use SRMS\Database\RecordNotFoundException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;

class SubjectController {
    protected DatabaseConnection $db;

    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    /**
     * Adds a new subject, preventing duplicate codes for the faculty/curriculum.
     */
    public function addSubject(string $facultyEmail, string $subjectName, string $subjectCode, string $classAssigned = 'Not Assigned'): int {
        $subjectName = trim($subjectName);
        $subjectCode = strtoupper(trim($subjectCode));
        $classAssigned = trim($classAssigned);

        if (empty($subjectName) || empty($subjectCode)) {
            throw new ValidationException("Subject Name & Code are required!");
        }

        // Check for duplicate subject code
        $sql = "SELECT id FROM subjects WHERE UPPER(subject_code) = :code AND faculty_email = :email LIMIT 1";
        $existing = $this->db->fetchOne($sql, [
            ':code'  => $subjectCode,
            ':email' => $facultyEmail
        ]);

        if ($existing) {
            throw new UniqueConstraintException("Subject with code '$subjectCode' already assigned to this faculty!", 1062, null, 'subject_code', $subjectCode);
        }

        return $this->db->insert('subjects', [
            'faculty_email'  => $facultyEmail,
            'subject_name'   => $subjectName,
            'subject_code'   => $subjectCode,
            'class_assigned' => $classAssigned
        ]);
    }

    /**
     * Updates an existing subject.
     */
    public function updateSubject(int $id, string $subjectName, string $subjectCode, string $classAssigned): int {
        $subjectName = trim($subjectName);
        $subjectCode = strtoupper(trim($subjectCode));

        if (empty($subjectName) || empty($subjectCode)) {
            throw new ValidationException("Subject Name & Code are required!");
        }

        $existing = $this->db->fetchOne("SELECT * FROM subjects WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$existing) {
            throw new RecordNotFoundException("Subject with ID $id not found.");
        }

        // Check duplicate code excluding current record
        $dup = $this->db->fetchOne(
            "SELECT id FROM subjects WHERE UPPER(subject_code) = :code AND faculty_email = :email AND id != :id LIMIT 1",
            [
                ':code'  => $subjectCode,
                ':email' => $existing['faculty_email'],
                ':id'    => $id
            ]
        );

        if ($dup) {
            throw new UniqueConstraintException("Subject code '$subjectCode' is already used by another record.", 1062, null, 'subject_code', $subjectCode);
        }

        return $this->db->update('subjects', [
            'subject_name'   => $subjectName,
            'subject_code'   => $subjectCode,
            'class_assigned' => $classAssigned
        ], ['id' => $id]);
    }

    /**
     * Deletes a subject by ID.
     */
    public function deleteSubject(int $id): int {
        $existing = $this->db->fetchOne("SELECT id FROM subjects WHERE id = :id LIMIT 1", [':id' => $id]);
        if (!$existing) {
            throw new RecordNotFoundException("Subject with ID $id not found.");
        }

        return $this->db->delete('subjects', ['id' => $id]);
    }

    /**
     * Fetches all subjects for a given faculty.
     */
    public function getSubjectsByFaculty(string $facultyEmail): array {
        return $this->db->fetchAll(
            "SELECT * FROM subjects WHERE faculty_email = :email ORDER BY id DESC",
            [':email' => $facultyEmail]
        );
    }
}
