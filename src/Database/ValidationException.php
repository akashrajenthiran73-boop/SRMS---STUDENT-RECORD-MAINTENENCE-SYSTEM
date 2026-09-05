<?php
namespace SRMS\Database;

use Exception;

class ValidationException extends DatabaseException {
    protected array $errors = [];

    public function __construct(string $message = "Validation failed", array $errors = [], int $code = 422, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
