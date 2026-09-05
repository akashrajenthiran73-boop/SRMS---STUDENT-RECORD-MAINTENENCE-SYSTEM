<?php
namespace SRMS\Tests\Mocks;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class PDOMockBuilder {
    /**
     * Creates an in-memory SQLite PDO instance configured with foreign keys enabled.
     * Ideal for testing real PDO prepared statements, constraints, and transactions.
     */
    public static function createInMemoryPdo(): PDO {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // Create standard SRMS schema in memory
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL,
                reg_no TEXT,
                college_code TEXT,
                status TEXT DEFAULT 'Active'
            );

            CREATE TABLE IF NOT EXISTS students (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                reg_no TEXT UNIQUE,
                email TEXT,
                dob TEXT,
                date_of_joining TEXT,
                date_of_leaving TEXT,
                photo_url TEXT,
                days_present INTEGER,
                working_days INTEGER,
                class_rank INTEGER,
                sem1_marks TEXT,
                sem2_marks TEXT,
                sem3_marks TEXT,
                sem4_marks TEXT,
                sem5_marks TEXT,
                sem6_marks TEXT
            );

            CREATE TABLE IF NOT EXISTS subjects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                faculty_email TEXT NOT NULL,
                subject_name TEXT NOT NULL,
                subject_code TEXT NOT NULL,
                class_assigned TEXT DEFAULT 'Not Assigned',
                UNIQUE(faculty_email, subject_code)
            );

            CREATE TABLE IF NOT EXISTS leave_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                applicant_id INTEGER NOT NULL,
                applicant_name TEXT NOT NULL,
                applicant_role TEXT NOT NULL,
                request_type TEXT NOT NULL,
                from_date TEXT NOT NULL,
                to_date TEXT NOT NULL,
                reason TEXT NOT NULL,
                status TEXT DEFAULT 'Pending',
                FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        return $pdo;
    }

    /**
     * Creates a simulated PDOException for a Foreign Key constraint violation.
     */
    public static function createForeignKeyException(string $message = "Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails"): PDOException {
        $e = new PDOException($message, 23000);
        $e->errorInfo = ['23000', 1452, $message];
        return $e;
    }

    /**
     * Creates a simulated PDOException for a Unique constraint violation.
     */
    public static function createUniqueConstraintException(string $message = "Integrity constraint violation: 1062 Duplicate entry 'test@example.com' for key 'users.email'"): PDOException {
        $e = new PDOException($message, 23000);
        $e->errorInfo = ['23000', 1062, $message];
        return $e;
    }

    /**
     * Creates a PHPUnit MockObject for PDOStatement.
     */
    public static function createMockStatement(TestCase $testCase, array $returns = [], int $rowCount = 1): PDOStatement {
        $stmt = $testCase->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn($rowCount);
        $stmt->method('fetch')->willReturn($returns[0] ?? false);
        $stmt->method('fetchAll')->willReturn($returns);
        return $stmt;
    }

    /**
     * Creates a PHPUnit MockObject for PDO.
     */
    public static function createMockPdo(TestCase $testCase, ?PDOStatement $defaultStmt = null): PDO {
        $pdo = $testCase->createMock(PDO::class);

        if ($defaultStmt !== null) {
            $pdo->method('prepare')->willReturn($defaultStmt);
        }

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('inTransaction')->willReturn(false);
        $pdo->method('lastInsertId')->willReturn('1');

        return $pdo;
    }
}
