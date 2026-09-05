<?php
namespace SRMS\Tests\Database;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use SRMS\Database\DatabaseConnection;
use SRMS\Database\DatabaseException;
use SRMS\Database\ForeignKeyConstraintException;
use SRMS\Database\UniqueConstraintException;
use SRMS\Database\ValidationException;
use SRMS\Tests\Mocks\PDOMockBuilder;

class DatabaseExceptionHandlingTest extends TestCase {

    public function testMockPdoForeignKeyViolationThrowsForeignKeyConstraintException(): void {
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willThrowException(
            PDOMockBuilder::createForeignKeyException("1452 Cannot add or update a child row: a foreign key constraint fails")
        );

        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        $db = new DatabaseConnection($mockPdo);

        $this->expectException(ForeignKeyConstraintException::class);
        $this->expectExceptionMessage("Foreign key violation: Referenced parent record does not exist.");

        $db->execute("INSERT INTO leave_requests (applicant_id) VALUES (:id)", [':id' => 9999]);
    }

    public function testMockPdoUniqueConstraintViolationThrowsUniqueConstraintException(): void {
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willThrowException(
            PDOMockBuilder::createUniqueConstraintException("1062 Duplicate entry 'admin@college.edu' for key 'email'")
        );

        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        $db = new DatabaseConnection($mockPdo);

        $this->expectException(UniqueConstraintException::class);
        $this->expectExceptionMessage("Unique constraint violation: Duplicate entry found.");

        $db->execute("INSERT INTO users (email) VALUES (:email)", [':email' => 'admin@college.edu']);
    }

    public function testRealPdoForeignKeyConstraintEnforcement(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        // Try to insert a leave request for user ID 99999 (which does not exist)
        $this->expectException(ForeignKeyConstraintException::class);

        $db->insert('leave_requests', [
            'applicant_id'   => 99999, // does not exist in users table
            'applicant_name' => 'Ghost User',
            'applicant_role' => 'Student',
            'request_type'   => 'Leave',
            'from_date'      => '2026-09-01',
            'to_date'        => '2026-09-02',
            'reason'         => 'Medical'
        ]);
    }

    public function testRealPdoUniqueConstraintEnforcement(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        $db->insert('users', [
            'name'     => 'Original User',
            'email'    => 'unique@college.edu',
            'password' => 'secret',
            'role'     => 'Student'
        ]);

        // Attempt duplicate insert
        $this->expectException(UniqueConstraintException::class);

        $db->insert('users', [
            'name'     => 'Duplicate User',
            'email'    => 'unique@college.edu',
            'password' => 'secret2',
            'role'     => 'Student'
        ]);
    }

    public function testTransactionRollbackOnError(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        try {
            $db->transaction(function (DatabaseConnection $conn) {
                // First insert succeeds
                $conn->insert('users', [
                    'name'     => 'Tx User',
                    'email'    => 'tx@college.edu',
                    'password' => 'secret',
                    'role'     => 'Student'
                ]);

                // Second insert fails due to duplicate email
                $conn->insert('users', [
                    'name'     => 'Tx User 2',
                    'email'    => 'tx@college.edu',
                    'password' => 'secret',
                    'role'     => 'Student'
                ]);
            });
        } catch (\Throwable $e) {
            // caught exception
        }

        // Entire transaction should be rolled back; Tx User should NOT exist
        $user = $db->fetchOne("SELECT * FROM users WHERE email = :email", [':email' => 'tx@college.edu']);
        $this->assertNull($user, "Transaction rollback failed; record was found in database!");
    }

    public function testTransactionCommitOnSuccess(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        $db->transaction(function (DatabaseConnection $conn) {
            $conn->insert('users', [
                'name'     => 'Committed User 1',
                'email'    => 'committed1@college.edu',
                'password' => 'secret',
                'role'     => 'Student'
            ]);
            $conn->insert('users', [
                'name'     => 'Committed User 2',
                'email'    => 'committed2@college.edu',
                'password' => 'secret',
                'role'     => 'Student'
            ]);
        });

        $count = $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE email LIKE 'committed%'");
        $this->assertEquals(2, $count['cnt']);
    }

    public function testUpdateAndEmptyConditionThrowsValidationException(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Update requires conditions to prevent accidental full-table modification");

        $db->update('users', ['name' => 'New Name'], []);
    }

    public function testDeleteAndEmptyConditionThrowsValidationException(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Delete requires conditions to prevent accidental full-table deletion");

        $db->delete('users', []);
    }

    public function testQueryLogTracksExecutedQueriesAndParameters(): void {
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $db = new DatabaseConnection($pdo);

        $db->fetchOne("SELECT * FROM users WHERE email = :email", [':email' => 'test@test.com']);

        $logs = $db->getQueryLog();
        $this->assertCount(1, $logs);
        $this->assertStringContainsString('SELECT * FROM users', $logs[0]['sql']);
        $this->assertEquals([':email' => 'test@test.com'], $logs[0]['params']);
    }
}
