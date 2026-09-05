<?php
namespace SRMS\Tests\Auth;

use PHPUnit\Framework\TestCase;
use SRMS\Auth\AuthController;
use SRMS\Database\DatabaseConnection;
use SRMS\Database\AuthenticationException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;
use SRMS\Tests\Mocks\PDOMockBuilder;

class AuthenticationTest extends TestCase {
    protected DatabaseConnection $db;
    protected AuthController $auth;

    protected function setUp(): void {
        parent::setUp();
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $this->db = new DatabaseConnection($pdo);
        $this->auth = new AuthController($this->db);

        // Seed default users for tests
        $this->auth->register('Super Admin User', 'superadmin@college.edu', 'AdminPass123!', 'Super Admin');
        $this->auth->register('Admin Staff', 'admin@college.edu', 'AdminPass123!', 'Admin');
        $this->auth->register('Dr. HOD', 'hod@college.edu', 'HodPass123!', 'HOD');
        $this->auth->register('Prof. Faculty', 'faculty@college.edu', 'FacultyPass123!', 'Faculty');
        $this->auth->register('Alice Student', 'student@college.edu', 'StudentPass123!', 'Student', '23CS101');
    }

    public function testSuperAdminLoginSuccess(): void {
        $result = $this->auth->login('superadmin@college.edu', 'AdminPass123!', 'Super Admin');

        $this->assertTrue($result['success']);
        $this->assertEquals('../admin/dashboard_admin.php', $result['redirect']);
        $this->assertEquals('Super Admin', $result['session']['role']);
        $this->assertEquals('superadmin@college.edu', $result['session']['email']);
    }

    public function testAdminLoginSuccess(): void {
        $result = $this->auth->login('admin@college.edu', 'AdminPass123!', 'Admin');

        $this->assertTrue($result['success']);
        $this->assertEquals('../admin/dashboard_admin.php', $result['redirect']);
        $this->assertEquals('Admin', $result['session']['role']);
    }

    public function testHodLoginSuccess(): void {
        $result = $this->auth->login('hod@college.edu', 'HodPass123!', 'HOD');

        $this->assertTrue($result['success']);
        $this->assertEquals('../HOD/dashboard_hod.php', $result['redirect']);
        $this->assertEquals('HOD', $result['session']['role']);
    }

    public function testFacultyLoginSuccess(): void {
        $result = $this->auth->login('faculty@college.edu', 'FacultyPass123!', 'Faculty');

        $this->assertTrue($result['success']);
        $this->assertEquals('../faculty/dashboard_faculty.php', $result['redirect']);
        $this->assertEquals('Faculty', $result['session']['role']);
    }

    public function testStudentLoginWithCorrectRegisterNumber(): void {
        $result = $this->auth->login('student@college.edu', 'StudentPass123!', 'Student', '23CS101');

        $this->assertTrue($result['success']);
        $this->assertEquals('../student/dashboard_student.php', $result['redirect']);
        $this->assertEquals('Student', $result['session']['role']);
        $this->assertEquals('23CS101', $result['session']['reg_no']);
    }

    public function testStudentLoginFailsWithMismatchedRegisterNumber(): void {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid Register Number! Please enter your correct Register Number.");

        $this->auth->login('student@college.edu', 'StudentPass123!', 'Student', 'WRONG_REG');
    }

    public function testStudentLoginFailsWithMissingRegisterNumber(): void {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid Register Number! Please enter your correct Register Number.");

        $this->auth->login('student@college.edu', 'StudentPass123!', 'Student', '');
    }

    public function testLoginFailsWithInvalidPassword(): void {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid Email, Password or Role");

        $this->auth->login('faculty@college.edu', 'WrongPassword!', 'Faculty');
    }

    public function testLoginFailsWithNonExistentEmail(): void {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid Email, Password or Role");

        $this->auth->login('ghost@college.edu', 'AnyPass123!', 'Faculty');
    }

    public function testLoginFailsWithMismatchedRole(): void {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage("Invalid Email, Password or Role");

        // Student trying to log in as Faculty
        $this->auth->login('student@college.edu', 'StudentPass123!', 'Faculty');
    }

    public function testCaptchaValidationSuccessCaseInsensitive(): void {
        $result = $this->auth->login(
            'admin@college.edu',
            'AdminPass123!',
            'Admin',
            null,
            'aB3d',       // user entered
            'AB3D'        // session stored
        );

        $this->assertTrue($result['success']);
    }

    public function testCaptchaValidationFailsOnMismatch(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Invalid Verification Code (Captcha)! Please try again.");

        $this->auth->login(
            'admin@college.edu',
            'AdminPass123!',
            'Admin',
            null,
            '1111',
            '9999'
        );
    }

    public function testRememberMeCookieGenerated(): void {
        $result = $this->auth->login(
            'admin@college.edu',
            'AdminPass123!',
            'Admin',
            null,
            null,
            null,
            true // remember me checked
        );

        $this->assertNotNull($result['remember_me']);
        $this->assertEquals('remember_email', $result['remember_me']['cookie_name']);
        $this->assertEquals('admin@college.edu', $result['remember_me']['cookie_value']);
        $this->assertTrue($result['remember_me']['httponly']);
        $this->assertEquals('Lax', $result['remember_me']['samesite']);
    }

    public function testRoleBasedAccessControl(): void {
        $adminSession = ['user_id' => 1, 'role' => 'Admin'];
        $facultySession = ['user_id' => 2, 'role' => 'Faculty'];
        $studentSession = ['user_id' => 3, 'role' => 'Student'];

        // Admin page check
        $this->assertTrue($this->auth->checkRole($adminSession, ['Admin', 'Super Admin']));
        $this->assertFalse($this->auth->checkRole($facultySession, ['Admin', 'Super Admin']));
        $this->assertFalse($this->auth->checkRole($studentSession, ['Admin', 'Super Admin']));

        // Faculty page check
        $this->assertTrue($this->auth->checkRole($facultySession, ['Faculty', 'HOD', 'Admin']));
        $this->assertFalse($this->auth->checkRole($studentSession, ['Faculty', 'HOD', 'Admin']));

        // Student page check
        $this->assertTrue($this->auth->checkRole($studentSession, ['Student']));
        $this->assertFalse($this->auth->checkRole($adminSession, ['Student']));
    }

    public function testSessionExpiration(): void {
        $activeSession = [
            'user_id' => 1,
            'role' => 'Admin',
            'last_activity' => time() - 300 // 5 minutes ago
        ];
        $this->assertTrue($this->auth->isSessionActive($activeSession, 1800));

        $expiredSession = [
            'user_id' => 1,
            'role' => 'Admin',
            'last_activity' => time() - 3600 // 1 hour ago
        ];
        $this->assertFalse($this->auth->isSessionActive($expiredSession, 1800));

        $noUserSession = ['role' => 'Admin'];
        $this->assertFalse($this->auth->isSessionActive($noUserSession));
    }

    public function testLogoutClearsSession(): void {
        $session = ['user_id' => 5, 'role' => 'Student', 'name' => 'Alice'];
        $this->auth->logout($session);
        $this->assertEmpty($session);
    }

    public function testRegistrationDuplicateEmailPrevented(): void {
        $this->expectException(UniqueConstraintException::class);
        $this->expectExceptionMessage("This Email ID or Register Number is already registered! Please Login.");

        $this->auth->register('Duplicate Admin', 'admin@college.edu', 'AnyPass123!', 'Admin');
    }
}
