<?php
namespace SRMS\Auth;

use SRMS\Database\DatabaseConnection;
use SRMS\Database\AuthenticationException;
use SRMS\Database\UnauthorizedException;
use SRMS\Database\ValidationException;
use SRMS\Database\UniqueConstraintException;

class AuthController {
    protected DatabaseConnection $db;

    public const ROLE_SUPER_ADMIN = 'Super Admin';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_HOD = 'HOD';
    public const ROLE_FACULTY = 'Faculty';
    public const ROLE_STUDENT = 'Student';

    public const REDIRECTS = [
        'Super Admin' => '../admin/dashboard_admin.php',
        'Admin'       => '../admin/dashboard_admin.php',
        'HOD'         => '../HOD/dashboard_hod.php',
        'Faculty'     => '../faculty/dashboard_faculty.php',
        'Student'     => '../student/dashboard_student.php'
    ];

    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }

    /**
     * Authenticates user with credentials, role, captcha, and register number checks.
     */
    public function login(
        string $email,
        string $password,
        string $role,
        ?string $regNo = null,
        ?string $captcha = null,
        ?string $sessionCaptcha = null,
        bool $remember = false
    ): array {
        // 1. Captcha Validation
        if ($sessionCaptcha !== null) {
            if (empty($captcha) || strtolower(trim($captcha)) !== strtolower(trim($sessionCaptcha))) {
                throw new ValidationException("Invalid Verification Code (Captcha)! Please try again.");
            }
        }

        $email = trim($email);
        $role = trim($role);

        if (empty($email) || empty($password) || empty($role)) {
            throw new ValidationException("Email, password, and role are required.");
        }

        // 2. Fetch User safely with Prepared Statement
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        $user = $this->db->fetchOne($sql, [':email' => $email]);

        if (!$user) {
            throw new AuthenticationException("Invalid Email, Password or Role");
        }

        // 3. Verify Password
        if (!password_verify($password, $user['password'])) {
            throw new AuthenticationException("Invalid Email, Password or Role");
        }

        // 4. Verify Role
        if (strtolower($user['role']) !== strtolower($role)) {
            throw new AuthenticationException("Invalid Email, Password or Role");
        }

        // 5. Strict Register Number Validation for Student
        if (strtolower($role) === 'student') {
            $dbRegNo = trim($user['reg_no'] ?? '');
            $inputRegNo = trim($regNo ?? '');

            if (empty($inputRegNo) || $dbRegNo !== $inputRegNo) {
                throw new AuthenticationException("Invalid Register Number! Please enter your correct Register Number.");
            }
        }

        // 6. Role-Based Redirect Determination
        $normalizedRole = null;
        foreach (self::REDIRECTS as $key => $target) {
            if (strtolower($key) === strtolower($role)) {
                $normalizedRole = $key;
                break;
            }
        }

        $redirectUrl = self::REDIRECTS[$normalizedRole] ?? '../auth/login.php';

        // 7. Generate Session Data
        $sessionData = [
            'user_id'       => $user['id'] ?? 1,
            'role'          => $normalizedRole ?? $user['role'],
            'name'          => $user['name'] ?? '',
            'email'         => $user['email'],
            'reg_no'        => $user['reg_no'] ?? '',
            'college_code'  => $user['college_code'] ?? '',
            'last_activity' => time()
        ];

        return [
            'success'      => true,
            'user'         => $user,
            'session'      => $sessionData,
            'redirect'     => $redirectUrl,
            'remember_me'  => $remember ? [
                'cookie_name'  => 'remember_email',
                'cookie_value' => $email,
                'expires'      => time() + (86400 * 30),
                'httponly'     => true,
                'samesite'     => 'Lax'
            ] : null
        ];
    }

    /**
     * Registers a new user.
     */
    public function register(string $name, string $email, string $password, string $role, string $regNo = ''): int {
        $name = trim($name);
        $email = trim($email);
        $role = trim($role);
        $regNo = trim($regNo);

        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            throw new ValidationException("All registration fields are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format.");
        }

        // Check if email or reg_no already exists
        $sql = "SELECT id FROM users WHERE email = :email" . (!empty($regNo) ? " OR reg_no = :reg_no" : "") . " LIMIT 1";
        $params = [':email' => $email];
        if (!empty($regNo)) {
            $params[':reg_no'] = $regNo;
        }

        $existing = $this->db->fetchOne($sql, $params);
        if ($existing) {
            throw new UniqueConstraintException("This Email ID or Register Number is already registered! Please Login.", 1062, null, 'email/reg_no', $email);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        return $this->db->insert('users', [
            'name'     => $name,
            'email'    => $email,
            'password' => $hashedPassword,
            'role'     => $role,
            'reg_no'   => $regNo
        ]);
    }

    /**
     * Checks Role-Based Access Control (RBAC).
     */
    public function checkRole(array $session, array $allowedRoles): bool {
        if (!isset($session['user_id']) || !isset($session['role'])) {
            return false;
        }

        $userRole = strtolower(trim($session['role']));
        $allowed = array_map('strtolower', array_map('trim', $allowedRoles));

        return in_array($userRole, $allowed, true);
    }

    /**
     * Verifies if a user session is active and unexpired.
     */
    public function isSessionActive(array $session, int $timeoutSeconds = 1800): bool {
        if (!isset($session['user_id'])) {
            return false;
        }

        if (isset($session['last_activity'])) {
            if ((time() - $session['last_activity']) > $timeoutSeconds) {
                return false; // Session expired
            }
        }

        return true;
    }

    /**
     * Performs logout and clears session data.
     */
    public function logout(array &$session): void {
        $session = [];
    }
}
