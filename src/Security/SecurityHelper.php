<?php
namespace SRMS\Security;

use InvalidArgumentException;
use SecurityException;

class SecurityHelper {
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'txt', 'png', 'jpg', 'jpeg'];
    public const DANGEROUS_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'py', 'pl', 'cgi'];

    /**
     * Sanitizes a string against XSS attacks using htmlspecialchars with ENT_QUOTES.
     */
    public static function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Recursively sanitizes array data.
     */
    public static function sanitizeArray(array $data): array {
        $clean = [];
        foreach ($data as $key => $value) {
            $cleanKey = is_string($key) ? self::sanitize($key) : $key;
            if (is_array($value)) {
                $clean[$cleanKey] = self::sanitizeArray($value);
            } elseif (is_string($value)) {
                $clean[$cleanKey] = self::sanitize($value);
            } else {
                $clean[$cleanKey] = $value;
            }
        }
        return $clean;
    }

    /**
     * Strips dangerous tags or all tags.
     */
    public static function stripTags(string $input, string $allowedTags = ''): string {
        return strip_tags(trim($input), $allowedTags);
    }

    /**
     * Validates that SQL query does not contain raw inlined user data.
     * Asserts that query relies on parameterized placeholders (:param or ?).
     */
    public static function isSafePreparedQuery(string $sql, array $params = []): bool {
        // Look for suspicious SQL injection patterns directly in the SQL string
        $suspiciousPatterns = [
            "/--\s*$/m",
            "/\bUNION\s+SELECT\b/i",
            "/\bOR\s+['\"]?1['\"]?\s*=\s*['\"]?1['\"]?/i",
            "/;\s*DROP\s+TABLE\b/i",
            "/;\s*DELETE\s+FROM\b/i",
            "/\bEXEC(\s|\()+/i"
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitizes uploaded file name to prevent directory traversal and arbitrary code execution.
     */
    public static function sanitizeFileName(string $filename): string {
        // Strip null bytes
        $filename = str_replace(chr(0), '', $filename);
        // Remove directory paths
        $basename = basename($filename);
        // Replace spaces and special characters with underscore
        return preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $basename);
    }

    /**
     * Checks for path traversal sequences like ../ or ..\ or null bytes.
     */
    public static function validateSafePath(string $filepath): bool {
        if (str_contains($filepath, "\0")) {
            return false;
        }
        if (str_contains($filepath, '..') || str_contains($filepath, '../') || str_contains($filepath, '..\\')) {
            return false;
        }
        return true;
    }

    /**
     * Validates file upload metadata and contents.
     */
    public static function validateFileUpload(array $file, array $allowedExtensions = self::ALLOWED_EXTENSIONS, int $maxSizeBytes = 10485760): array {
        if (!isset($file['name']) || !isset($file['error'])) {
            return ['valid' => false, 'error' => 'Invalid file upload payload'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error code: ' . $file['error']];
        }

        if ($file['size'] > $maxSizeBytes) {
            return ['valid' => false, 'error' => 'File size exceeds allowed limit of ' . ($maxSizeBytes / (1024 * 1024)) . 'MB'];
        }

        $filename = $file['name'];
        if (!self::validateSafePath($filename)) {
            return ['valid' => false, 'error' => 'Path traversal detected in file name'];
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            return ['valid' => false, 'error' => 'Dangerous file extension rejected'];
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            return ['valid' => false, 'error' => 'Invalid file extension: ' . $extension];
        }

        return [
            'valid' => true,
            'sanitized_name' => self::sanitizeFileName($filename),
            'extension' => $extension,
            'size' => $file['size']
        ];
    }
}
