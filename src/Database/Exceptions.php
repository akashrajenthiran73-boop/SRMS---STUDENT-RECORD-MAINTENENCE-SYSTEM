<?php
namespace SRMS\Database;

use Exception;

class DatabaseException extends Exception {}

class ForeignKeyConstraintException extends DatabaseException {
    protected $table;
    protected $constraint;

    public function __construct(string $message = "Foreign key constraint violation", int $code = 1452, ?Exception $previous = null, ?string $table = null, ?string $constraint = null) {
        parent::__construct($message, $code, $previous);
        $this->table = $table;
        $this->constraint = $constraint;
    }

    public function getTable(): ?string {
        return $this->table;
    }

    public function getConstraint(): ?string {
        return $this->constraint;
    }
}

class UniqueConstraintException extends DatabaseException {
    protected $field;
    protected $value;

    public function __construct(string $message = "Unique constraint violation", int $code = 1062, ?Exception $previous = null, ?string $field = null, ?string $value = null) {
        parent::__construct($message, $code, $previous);
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): ?string {
        return $this->field;
    }

    public function getValue(): ?string {
        return $this->value;
    }
}

class RecordNotFoundException extends DatabaseException {}

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

class AuthenticationException extends DatabaseException {}

class UnauthorizedException extends DatabaseException {}
