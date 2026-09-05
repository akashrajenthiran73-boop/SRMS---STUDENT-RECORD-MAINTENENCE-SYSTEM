<?php
namespace SRMS\Database;

use Exception;

class UniqueConstraintException extends DatabaseException {
    protected ?string $field;
    protected ?string $value;

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
