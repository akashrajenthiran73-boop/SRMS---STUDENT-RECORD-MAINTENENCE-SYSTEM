<?php
namespace SRMS\Tests\Security;

use PHPUnit\Framework\TestCase;
use SRMS\Security\SecurityHelper;
use SRMS\Database\DatabaseConnection;
use SRMS\Tests\Mocks\PDOMockBuilder;

class SecurityInputSanitizationTest extends TestCase {
    protected DatabaseConnection $db;

    protected function setUp(): void {
        parent::setUp();
        $pdo = PDOMockBuilder::createInMemoryPdo();
        $this->db = new DatabaseConnection($pdo);
    }

    public function testSqlInjectionPayloadsTreatedAsLiteralsInPreparedStatement(): void {
        // Insert a normal user
        $this->db->insert('users', [
            'name'     => 'Real Student',
            'email'    => 'real@college.edu',
            'password' => 'hashed',
            'role'     => 'Student',
            'reg_no'   => '23CS101'
        ]);

        // Attempt SQL injection via parameterized query
        $sqlInjectionPayloads = [
            "' OR '1'='1",
            "admin' --",
            "1' UNION SELECT 'hacked', 'hacked@x.com', 'p', 'Admin', '00', '00', 'Active' --",
            "'; DROP TABLE students; --",
            "\" OR \"\"=\""
        ];

        foreach ($sqlInjectionPayloads as $payload) {
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = :email",
                [':email' => $payload]
            );
            // Payload should be treated as literal email, so NO user is found and no SQL syntax error or injection happens
            $this->assertNull($user, "Payload was not treated as literal string: $payload");
        }
    }

    public function testSafePreparedQueryDetection(): void {
        $safeQuery = "SELECT * FROM students WHERE reg_no = :reg_no AND id = :id";
        $this->assertTrue(SecurityHelper::isSafePreparedQuery($safeQuery));

        // Malicious inlined queries
        $unsafeQueries = [
            "SELECT * FROM users WHERE email = 'admin@college.edu' --",
            "SELECT * FROM users WHERE id = 1 UNION SELECT name, email FROM admins",
            "SELECT * FROM students WHERE 1=1 OR '1'='1'",
            "SELECT * FROM students; DROP TABLE students;"
        ];

        foreach ($unsafeQueries as $unsafe) {
            $this->assertFalse(SecurityHelper::isSafePreparedQuery($unsafe), "Unsafe query passed detection: $unsafe");
        }
    }

    public function testXssSanitizationEscapesHtmlEntities(): void {
        $xssVectors = [
            "<script>alert('XSS')</script>" => "&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;",
            "<img src=x onerror=alert(1)>"   => "&lt;img src=x onerror=alert(1)&gt;",
            "\"><script>document.cookie</script>" => "&quot;&gt;&lt;script&gt;document.cookie&lt;/script&gt;",
            "O'Connor & Sons <tag>"         => "O&#039;Connor &amp; Sons &lt;tag&gt;"
        ];

        foreach ($xssVectors as $raw => $expected) {
            $sanitized = SecurityHelper::sanitize($raw);
            $this->assertEquals($expected, $sanitized);
            $this->assertStringNotContainsString("<script>", $sanitized);
            $this->assertStringNotContainsString("<img", $sanitized);
        }
    }

    public function testRecursiveArraySanitization(): void {
        $input = [
            'name' => '<b>John</b>',
            'details' => [
                'bio' => '<script>steal()</script>',
                'scores' => [10, 20, 30]
            ]
        ];

        $clean = SecurityHelper::sanitizeArray($input);

        $this->assertEquals('&lt;b&gt;John&lt;/b&gt;', $clean['name']);
        $this->assertEquals('&lt;script&gt;steal()&lt;/script&gt;', $clean['details']['bio']);
        $this->assertEquals([10, 20, 30], $clean['details']['scores']);
    }

    public function testFileUploadValidatesAllowedExtensions(): void {
        $validFile = [
            'name' => 'assignment_unit_1.pdf',
            'type' => 'application/pdf',
            'size' => 102400,
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK
        ];

        $result = SecurityHelper::validateFileUpload($validFile);
        $this->assertTrue($result['valid']);
        $this->assertEquals('pdf', $result['extension']);
    }

    public function testDangerousFileUploadExtensionsRejected(): void {
        $dangerousNames = [
            'backdoor.php',
            'exploit.phtml',
            'webshell.php5',
            'trojan.exe',
            'script.sh',
            'run.bat',
            'malicious.js',
            'archive.phar'
        ];

        foreach ($dangerousNames as $filename) {
            $dangerousFile = [
                'name' => $filename,
                'type' => 'application/octet-stream',
                'size' => 2048,
                'tmp_name' => '/tmp/evil',
                'error' => UPLOAD_ERR_OK
            ];

            $result = SecurityHelper::validateFileUpload($dangerousFile);
            $this->assertFalse($result['valid'], "Dangerous file $filename was not rejected!");
            $this->assertEquals('Dangerous file extension rejected', $result['error']);
        }
    }

    public function testPathTraversalInFileUploadRejected(): void {
        $traversalFiles = [
            '../../../../var/www/shell.pdf',
            '..\\..\\Windows\\System32\\calc.png',
            "legit.pdf\0.php"
        ];

        foreach ($traversalFiles as $name) {
            $file = [
                'name' => $name,
                'type' => 'application/pdf',
                'size' => 1000,
                'tmp_name' => '/tmp/file',
                'error' => UPLOAD_ERR_OK
            ];

            $result = SecurityHelper::validateFileUpload($file);
            $this->assertFalse($result['valid'], "Path traversal in $name was not blocked!");
        }
    }

    public function testSanitizeFileName(): void {
        $dirtyName = "My Syllabus & Notes (II Year) #1.pdf";
        $clean = SecurityHelper::sanitizeFileName($dirtyName);

        $this->assertEquals("My_Syllabus___Notes__II_Year___1.pdf", $clean);
        $this->assertDoesNotMatchRegularExpression('/[\s&#()]/', $clean);
    }
}
